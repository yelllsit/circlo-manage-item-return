"""End-to-end form tests using a disposable SQLite database and a temporary local server."""
import http.cookiejar
import os
from pathlib import Path
import re
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]

class Browser:
    def __init__(self, base):
        self.base = base
        self.client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def send(self, page, values=None, expected=200):
        data = urllib.parse.urlencode(values).encode() if values is not None else None
        try:
            response = self.client.open(urllib.request.Request(self.base+'/?page='+page, data=data))
        except urllib.error.HTTPError as error:
            response = error
        html = response.read().decode()
        assert response.status == expected, (page,response.status,expected,html[:500])
        assert 'Fatal error' not in html and '<b>Warning</b>' not in html
        return html,response.geturl()
    def post(self, page, values, expected=200):
        html,_=self.send(page)
        token=re.search(r'name="csrf" value="([^"]+)"',html).group(1)
        return self.send(page,{'csrf':token,**values},expected)
    def login(self, email):
        self.post('login',{'action':'login','email':email,'password':'Circlo123!'})

def main():
    with tempfile.TemporaryDirectory(prefix='circlo-return-http-') as tmp:
        with socket.socket() as sock:
            sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
        with open(Path(tmp)/'server.log','w') as log:
            server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(ROOT/'public'),str(ROOT/'public/index.php')],env={**os.environ,'CIRCLO_STORAGE':tmp},stdout=log,stderr=log)
            try:
                base=f'http://127.0.0.1:{port}'
                for _ in range(60):
                    try:urllib.request.urlopen(base,timeout=1);break
                    except Exception:time.sleep(.1)
                guest=Browser(base)
                _,url=guest.send('transactions');assert 'page=login' in url
                guest.send('login',{'action':'login','email':'khayel@circlo.test','password':'Circlo123!'},403)
                print('PASS login required and missing CSRF rejected')
                borrower,owner,other=Browser(base),Browser(base),Browser(base)
                borrower.login('khayel@circlo.test');owner.login('nadia@circlo.test');other.login('arya@circlo.test')
                borrower.send('confirmation&id=1',expected=403);owner.send('return&id=1',expected=403);other.send('transaction&id=1',expected=403)
                print('PASS borrower/owner/unrelated user access checks')
                for page in ['transactions','transactions&tab=completed','transaction&id=1','return&id=1','transaction&id=2']:
                    borrower.send(page)
                db=sqlite3.connect(Path(tmp)/'circlo.sqlite')
                assert db.execute('SELECT COUNT(*) FROM item_returns WHERE transaction_id=1').fetchone()[0]==0
                print('PASS viewing or cancelling return form makes no database change')
                borrower.post('return&id=1',{'action':'submit_return','transaction_id':1},422)
                borrower.post('return&id=1',{'action':'submit_return','transaction_id':1,'acknowledge':'1'})
                assert db.execute('SELECT status FROM transactions WHERE id=1').fetchone()[0]=='active'
                assert db.execute('SELECT status FROM item_returns WHERE transaction_id=1').fetchone()[0]=='awaiting'
                print('PASS return insert requires acknowledgement and awaits owner')
                borrower.post('transaction&id=1',{'action':'submit_return','transaction_id':1,'acknowledge':'1'},422)
                assert db.execute('SELECT COUNT(*) FROM item_returns WHERE transaction_id=1').fetchone()[0]==1
                owner.send('confirmation&id=1')
                assert db.execute('SELECT status FROM transactions WHERE id=1').fetchone()[0]=='active'
                owner.post('confirmation&id=1',{'action':'confirm_return','transaction_id':1,'acknowledge':'1'})
                assert db.execute('SELECT status FROM transactions WHERE id=1').fetchone()[0]=='completed'
                assert db.execute('SELECT status FROM item_returns WHERE transaction_id=1').fetchone()[0]=='confirmed'
                assert db.execute('SELECT availability FROM items WHERE id=1').fetchone()[0]=='available'
                print('PASS owner confirms; return, transaction and availability update together')
                owner.post('transaction&id=1',{'action':'confirm_return','transaction_id':1,'acknowledge':'1'},422)
                borrower.post('return&id=2',{'action':'submit_return','transaction_id':2,'acknowledge':'1'})
                assert db.execute('SELECT is_late FROM item_returns WHERE transaction_id=2').fetchone()[0]==1
                print('PASS duplicate confirmation blocked and overdue return recorded')
                html,_=borrower.send('transactions&tab=completed');assert 'Canon EOS 700D' in html
                borrower.post('transactions',{'action':'logout'})
                _,url=borrower.send('transaction&id=1');assert 'page=login' in url
                db.close()
                print('PASS completed history persists and logout removes access')
            finally:
                server.terminate();server.wait(timeout=5)
    print('All HTTP checks passed; temporary data removed.')

if __name__=='__main__':main()
