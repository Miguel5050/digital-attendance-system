// assets/js/main.js

document.addEventListener('DOMContentLoaded', () => {

    const showAlert = (containerId, message, type = 'danger') => {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        }
    };

    // ==========================================
    // 1. STUDENT LOGIC
    // ==========================================

    const attendanceForm = document.getElementById('attendance-form');
    if (attendanceForm) {
        
        // Attendance Form Submission (Manual or triggered by QR)
        attendanceForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = document.getElementById('mark-btn');
            btn.disabled = true;
            btn.innerText = 'Acquiring GPS...';

            const code = document.getElementById('attendance_code').value;
            const courseGroupId = document.getElementById('course_select').value;

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
                        body: JSON.stringify({ code: code, course_group_id: courseGroupId, lat: lat, lng: lng })
                    })
                    .then(res => res.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerText = 'Verify Course, GPS & Mark Present';
                        if (data.success) {
                            showAlert('alert-container', data.message, 'success');
                            attendanceForm.reset();
                            document.getElementById('claim-container').classList.add('d-none');
                            setTimeout(() => window.location.reload(), 2000);
                        } else {
                            showAlert('alert-container', data.error);
                            if (data.needs_claim) {
                                document.getElementById('claim-container').classList.remove('d-none');
                                document.getElementById('claim_session_id').value = data.session_id;
                            }
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showAlert('alert-container', 'Session expired/invalid role or Network error. Try relogging.');
                        btn.disabled = false;
                        btn.innerText = 'Verify Course, GPS & Mark Present';
                    });
                },
                (error) => {
                    showAlert('alert-container', 'Must allow GPS location to mark attendance. ' + error.message);
                    btn.disabled = false;
                    btn.innerText = 'Verify Course, GPS & Mark Present';
                },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
            );
        });

        // Claim Form Logic
        const claimForm = document.getElementById('claim-form');
        if (claimForm) {
            claimForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const btn = claimForm.querySelector('button');
                btn.disabled = true;
                
                fetch('api/submit_claim.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ 
                        session_id: document.getElementById('claim_session_id').value,
                        reason: document.getElementById('claim_reason').value
                    })
                })
                .then(r => r.json())
                .then(d => {
                    btn.disabled = false;
                    if(d.success) {
                        alert(d.message);
                        claimForm.reset();
                        document.getElementById('claim-container').classList.add('d-none');
                    } else {
                        alert(d.error);
                    }
                });
            });
        }
    }


    // ==========================================
    // 2. LECTURER LOGIC
    // ==========================================

    const startBtns = document.querySelectorAll('.start-session-btn');
    if (startBtns.length > 0) {
        
        // Start Session (with GPS and Duration)
        startBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const courseGroupId = e.target.getAttribute('data-cgid');
                const duration = document.getElementById('duration-' + courseGroupId).value;
                e.target.disabled = true;
                e.target.innerText = 'Locating...';
                
                if (!navigator.geolocation) {
                    alert('Geolocation not supported.');
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        fetch('api/create_session.php', {
                            method: 'POST',
                            credentials: 'include',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ 
                                course_group_id: courseGroupId,
                                duration_minutes: duration,
                                lat: lat,
                                lng: lng
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                window.location.reload();
                            } else {
                                showAlert('lecture-alert-container', data.error);
                                e.target.disabled = false;
                                e.target.innerText = 'Acquire GPS & Start';
                            }
                        })
                        .catch(err => {
                            showAlert('lecture-alert-container', 'Network error.');
                            e.target.disabled = false;
                            e.target.innerText = 'Acquire GPS & Start';
                        });
                    },
                    (error) => {
                        alert('You must allow GPS tracking to start a session as a lecturer.');
                        e.target.disabled = false;
                        e.target.innerText = 'Acquire GPS & Start';
                    },
                    { enableHighAccuracy: true }
                );
            });
        });

        // Close Session
        document.querySelectorAll('.close-session-btn').forEach(btn => {
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
                    if (data.success) { window.location.reload(); } 
                    else { alert(data.error); e.target.disabled = false; }
                });
            });
        });

        // Send Final Course Report
        document.querySelectorAll('.send-final-report-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const cgid = e.target.getAttribute('data-cgid');
                if(!confirm('Are you sure you want to finalize and send attendance statistics to the Administrator?')) return;
                
                e.target.disabled = true;
                e.target.innerText = 'Sending...';

                fetch('api/send_final_report.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ course_group_id: cgid })
                })
                .then(res => res.json())
                .then(data => {
                    e.target.disabled = false;
                    e.target.innerText = 'Send Final Report & Statistics';
                    if(data.success) {
                        alert(data.message);
                    } else {
                        alert(data.error);
                    }
                });
            });
        });

        // Generate QR Codes & Setup Live Polling
        const liveSessions = document.querySelectorAll('.live-session-container');
        if (liveSessions.length > 0) {
            liveSessions.forEach(container => {
                const sessionId = container.getAttribute('data-session-id');
                
                // Setup live polling
                setInterval(() => {
                    fetch(`api/get_live_attendance.php?session_id=${sessionId}`, { credentials: 'include' })
                    .then(r => r.json())
                    .then(data => {
                        if(data.success) {
                            const tbody = container.querySelector('tbody');
                            tbody.innerHTML = '';
                            let total = data.data.present.length + data.data.absent.length;
                            let pct = total > 0 ? Math.round((data.data.present.length / total) * 100) : 0;
                            
                            container.querySelector('.live-percentage-badge').innerText = pct + '%';
                            container.querySelector('.live-present-counter').innerText = 'Attendees: ' + data.data.present.length;

                            data.data.present.forEach(s => {
                                tbody.innerHTML += `<tr><td>${s.student_id}</td><td>${s.name}</td><td><span class="badge bg-success">Present</span></td><td>${s.time}</td></tr>`;
                            });
                            data.data.absent.forEach(s => {
                                tbody.innerHTML += `<tr><td>${s.student_id}</td><td>${s.name}</td><td><span class="badge bg-danger">Absent</span></td><td>--:--</td></tr>`;
                            });
                        }
                    });
                }, 5000);
            });
        }

        // Handle Claims
        const loadClaims = () => {
            const container = document.getElementById('claims-list-container');
            if(!container) return;
            fetch('api/get_claims.php', {credentials:'include'})
            .then(r => {
                if(!r.ok) throw new Error('Failed to load claims check PHP errors');
                return r.json();
            })
            .then(d=>{
                if(d.success) {
                    if(d.data.length === 0) {
                        container.innerHTML = '<div class="alert alert-light">No pending claims.</div>';
                        return;
                    }
                    container.innerHTML = d.data.map(c => `
                        <div class="card mb-2 shadow-sm border-0 bg-light">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between">
                                    <strong>${c.student_name} (${c.student_id ? c.student_id : 'N/A'})</strong>
                                    <small class="text-muted">${c.course_code}</small>
                                </div>
                                <p class="small mb-2 mt-1">"${c.reason}"</p>
                                <div>
                                    <button class="btn btn-sm btn-success claim-action-btn" data-id="${c.claim_id}" data-action="approve">Approve</button>
                                    <button class="btn btn-sm btn-danger claim-action-btn" data-id="${c.claim_id}" data-action="reject">Reject</button>
                                </div>
                            </div>
                        </div>
                    `).join('');

                    document.querySelectorAll('.claim-action-btn').forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            fetch('api/handle_claim.php', {
                                method: 'POST', credentials: 'include',
                                headers: {'Content-Type':'application/json'},
                                body: JSON.stringify({ claim_id: e.target.getAttribute('data-id'), action: e.target.getAttribute('data-action') })
                            }).then(r=>r.json()).then(resp=>{
                                if(resp.success) { loadClaims(); } else { alert(resp.error); }
                            });
                        });
                    });
                }
            }).catch(err => {
                console.error('Claims load error:', err);
                container.innerHTML = '<div class="alert alert-danger">Error loading claims.</div>';
            });
        };
        loadClaims();
        setInterval(loadClaims, 15000);
    }


    // ==========================================
    // 3. CHATBOX LOGIC (SHARED)
    // ==========================================
    
    const contactsContainer = document.getElementById('chat-contacts-container');
    const messagesContainer = document.getElementById('chat-messages-container');
    const chatForm = document.getElementById('chat-form');
    let currentChatUserId = null;

    if (contactsContainer) {
        
        // Load Contacts
        fetch('api/get_contacts.php', {credentials:'include'}).then(r=>r.json()).then(d => {
            if(d.success) {
                contactsContainer.innerHTML = d.data.length ? d.data.map(c => `
                    <div class="p-2 border-bottom cursor-pointer chat-contact-card" style="cursor:pointer;" data-id="${c.id}" data-name="${c.name}">
                        <strong>${c.name}</strong><br>
                        <small class="text-muted">${c.student_id ? 'Student ' + c.student_id : 'Lecturer (' + c.course_code + ')'}</small>
                    </div>
                `).join('') : '<div class="p-2 text-muted">No contacts</div>';

                document.querySelectorAll('.chat-contact-card').forEach(card => {
                    card.addEventListener('click', (e) => {
                        const target = e.currentTarget;
                        document.querySelectorAll('.chat-contact-card').forEach(c=>c.classList.remove('bg-light'));
                        target.classList.add('bg-light');
                        
                        currentChatUserId = target.getAttribute('data-id');
                        document.getElementById('chat-receiver-id').value = currentChatUserId;
                        chatForm.classList.remove('d-none');
                        loadMessages();
                    });
                });
            }
        });

        // Load Messages
        const loadMessages = () => {
            if(!currentChatUserId) return;
            fetch(`api/get_messages.php?with_id=${currentChatUserId}`, {credentials:'include'}).then(r=>r.json()).then(d=>{
                if(d.success) {
                    messagesContainer.innerHTML = d.data.length ? d.data.map(m => `
                        <div class="message-bubble ${m.sender_id == USER_ID ? 'message-sent' : 'message-received'}">
                            <strong>${m.sender_id == USER_ID ? 'You' : m.sender_name}</strong><br>
                            ${m.message}
                            <div class="small text-end mt-1" style="opacity:0.7; font-size:10px;">${new Date(m.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                        </div>
                    `).join('') : '<div class="text-center text-muted mt-5">No messages yet. Start the conversation!</div>';
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            });
        };

        // Send Message
        if(chatForm) {
            chatForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const input = document.getElementById('chat-input');
                const message = input.value;
                input.value = '';
                
                fetch('api/send_message.php', {
                    method: 'POST', credentials:'include',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({ receiver_id: currentChatUserId, message: message })
                }).then(r=>r.json()).then(d=>{
                    if(d.success) { loadMessages(); } else { alert(d.error); }
                });
            });
        }

        setInterval(loadMessages, 5000); // Live poll messages
    }


    // ==========================================
    // 4. ADMIN LOGIC (Existing)
    // ==========================================
    const csvForm = document.getElementById('csv-upload-form');
    if (csvForm) {
        // Keeps original admin implementation active
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
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showAlert('admin-alert', data.error);
                }
            });
        });
    }

});
