// Demo identity helpers only; ownership checks are always enforced on the server.
document.querySelectorAll('[data-demo]').forEach(button => {
  button.addEventListener('click', () => {
    const email = document.querySelector('[name="email"]');
    const password = document.querySelector('[name="password"]');
    email.value = button.dataset.demo;
    password.value = 'Circlo123!';
    document.querySelector('.login-panel .btn').focus();
  });
});
