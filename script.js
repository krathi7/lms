document.getElementById('loginForm').addEventListener('submit', function (e) {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();

    if (username === '' || password === '') {
        e.preventDefault();
        document.getElementById('error-message').innerText = 'All fields are required.';
    } else {
        document.getElementById('error-message').innerText = '';
    }
});
