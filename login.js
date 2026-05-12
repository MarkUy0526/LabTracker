document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('login-form');
  const guestSection = document.getElementById('guest-section');

  loginForm.style.display = 'block';
  guestSection.style.display = 'block';

  loginForm.addEventListener('submit', function (e) {
    e.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();

    if (!username || !password) {
      alert('Please enter username and password.');
      return;
    }

    fetch('admin_login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password }),
    })
      .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
      })
      .then(data => {
        if (data.status === 'success') {
          window.location.href = 'admin.php';
        } else {
          alert(data.message);
        }
      })
      .catch(error => {
        console.error('AJAX request failed:', error);
        alert('AJAX request failed. Check console for details.');
      });
  });

  document.getElementById('guestLoginBtn').addEventListener('click', () => {
    fetch('guest_login.php')
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          alert(`Your borrower login number is: ${data.guest_id}`);
          window.location.href = 'guest.php';
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Borrower login request failed.');
      });
  });

  function fetchRecentBorrowers() {
    fetch('fetch_recent_guests.php')
      .then(response => response.json())
      .then(data => {
        if (data.success && Array.isArray(data.data)) {
          const list = document.getElementById('recent-guests-list');
          list.innerHTML = '';

          data.data.forEach(item => {
            const li = document.createElement('li');
            li.textContent = item.guest_number;
            li.classList.add(`status-${item.status.toLowerCase()}`);

            const statusSpan = document.createElement('span');
            statusSpan.textContent = item.status.charAt(0).toUpperCase() + item.status.slice(1);
            li.appendChild(statusSpan);

            list.appendChild(li);
          });
        }
      })
      .catch(err => {
        console.error('Failed to load recent borrowers:', err);
      });
  }

  fetchRecentBorrowers();
  setInterval(fetchRecentBorrowers, 30000);
});
