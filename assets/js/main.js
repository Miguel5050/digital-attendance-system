// assets/js/main.js

document.addEventListener('DOMContentLoaded', () => {

    // Helper for alerts
    const showAlert = (containerId, message, type = 'danger') => {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        }
    };

    // STUDENT: Mark Attendance
    const attendanceForm = document.getElementById('attendance-form');
    if (attendanceForm) {
        attendanceForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = document.getElementById('mark-btn');
            btn.disabled = true;
            btn.innerText = 'Acquiring GPS...';

            const code = document.getElementById('attendance_code').value;

            if (!navigator.geolocation) {
                showAlert('alert-container', 'Geolocation is not supported by your browser.');
                btn.disabled = false;
                btn.innerText = 'Verify GPS & Mark Present';
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    btn.innerText = 'Submitting...';

                    fetch('api/mark_attendance.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            code: code,
                            lat: lat,
                            lng: lng
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerText = 'Verify GPS & Mark Present';
                        if (data.success) {
                            showAlert('alert-container', data.message, 'success');
                            attendanceForm.reset();
                            setTimeout(() => window.location.reload(), 2000);
                        } else {
                            showAlert('alert-container', data.error);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showAlert('alert-container', 'Network error occurred.');
                        btn.disabled = false;
                        btn.innerText = 'Verify GPS & Mark Present';
                    });
                },
                (error) => {
                    showAlert('alert-container', 'Must allow GPS location to mark attendance. ' + error.message);
                    btn.disabled = false;
                    btn.innerText = 'Verify GPS & Mark Present';
                },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
            );
        });
    }

    // LECTURER: Start Session
    const startBtns = document.querySelectorAll('.start-session-btn');
    startBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const courseGroupId = e.target.getAttribute('data-cgid');
            e.target.disabled = true;
            
            fetch('api/create_session.php', {
                method: 'POST',
                credentials: 'include',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ course_group_id: courseGroupId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    showAlert('lecture-alert-container', data.error);
                    e.target.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                showAlert('lecture-alert-container', 'Network error.');
                e.target.disabled = false;
            });
        });
    });

    // LECTURER: Close Session
    const closeBtns = document.querySelectorAll('.close-session-btn');
    closeBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const sessionId = e.target.getAttribute('data-id');
            e.target.disabled = true;

            fetch('api/close_session.php', {
                method: 'POST',
                credentials: 'include',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ session_id: sessionId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error);
                    e.target.disabled = false;
                }
            });
        });
    });

    // LECTURER: Send Report
    const reportBtns = document.querySelectorAll('.send-report-btn');
    reportBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const sessionId = e.target.getAttribute('data-id');
            e.target.disabled = true;
            e.target.innerText = 'Sending...';

            fetch('api/send_report.php', {
                method: 'POST',
                credentials: 'include',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ session_id: sessionId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    e.target.innerText = 'Sent';
                } else {
                    alert(data.error);
                    e.target.disabled = false;
                    e.target.innerText = 'Send Report';
                }
            })
            .catch(err => {
                console.error(err);
                alert('Network error.');
                e.target.disabled = false;
            });
        });
    });

    // ADMIN: Upload CSV
    const csvForm = document.getElementById('csv-upload-form');
    if (csvForm) {
        csvForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = document.getElementById('upload-btn');
            const fileInput = document.getElementById('csv_file');
            
            if (fileInput.files.length === 0) return;

            btn.disabled = true;
            btn.innerText = 'Processing...';

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            fetch('api/upload_enrollment.php', {
                method: 'POST',
                credentials: 'include',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerText = 'Upload & Sync Data';
                if (data.success) {
                    showAlert('admin-alert', data.message, 'success');
                    csvForm.reset();
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showAlert('admin-alert', data.error);
                }
            })
            .catch(err => {
                console.error(err);
                showAlert('admin-alert', 'Network error occurred. Check formatting.');
                btn.disabled = false;
                btn.innerText = 'Upload & Sync Data';
            });
        });
    }

});
