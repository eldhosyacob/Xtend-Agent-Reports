$(document).ready(function() {
    // Pagination state
    let allUsers = [];
    let currentPage = 1;
    const pageSize = 10;

    // Load users on initialization
    loadUsers();

    // Toggle password visibility (generic class handler)
    $(document).on('click', '.btn-toggle-pwd', function() {
        const targetId = $(this).data('target');
        const $input = $('#' + targetId);
        const type = $input.attr('type') === 'password' ? 'text' : 'password';
        $input.attr('type', type);
        
        const $icon = $(this).find('i');
        if (type === 'password') {
            $icon.removeClass('fa-eye-slash').addClass('fa-eye');
        } else {
            $icon.removeClass('fa-eye').addClass('fa-eye-slash');
        }
    });

    // Open Add User Modal
    $('#btnAddUser').on('click', function() {
        $('#addUserForm')[0].reset();
        
        // Reset password fields to hidden state
        $('#modalPassword, #modalConfirmPassword').attr('type', 'password');
        $('.btn-toggle-pwd i').removeClass('fa-eye-slash').addClass('fa-eye');
        
        $('#addUserModal').addClass('active');
        $('#modalUsername').focus();
    });

    // Close Add User Modal
    $('#btnCancelAddUser, #btnCloseModalX').on('click', function() {
        $('#addUserModal').removeClass('active');
    });

    // Close Add User Modal on clicking backdrop
    $('#addUserModal').on('click', function(e) {
        if (e.target === this) {
            $('#addUserModal').removeClass('active');
        }
    });

    // Open Edit User Modal (delegate event to dynamically created rows)
    $(document).on('click', '.btn-edit-user', function() {
        const id = $(this).data('id');
        const username = $(this).data('username');
        const name = $(this).data('name');
        const dept = $(this).data('dept');
        const role = $(this).data('role');

        // Reset edit form fields
        $('#editUserForm')[0].reset();
        $('#editPassword, #editConfirmPassword').attr('type', 'password');
        $('.btn-toggle-pwd i').removeClass('fa-eye-slash').addClass('fa-eye');

        // Populate fields
        $('#editUserId').val(id);
        $('#editUsername').val(username);
        $('#editRealName').val(name);
        $('#editDepartment').val(dept || 'Voice Logger');
        $('#editUserType').val(role || 'user');

        $('#editUserModal').addClass('active');
        $('#editPassword').focus();
    });

    // Close Edit User Modal
    $('#btnCancelEditUser, #btnCloseEditModalX').on('click', function() {
        $('#editUserModal').removeClass('active');
    });

    // Close Edit User Modal on clicking backdrop
    $('#editUserModal').on('click', function(e) {
        if (e.target === this) {
            $('#editUserModal').removeClass('active');
        }
    });

    // Alert helper function
    function showAlert(type, message) {
        const $alert = $('#alertBox');
        const $icon = $alert.find('.id-icon');
        
        $alert.removeClass('profile-alert-success profile-alert-error');
        
        if (type === 'success') {
            $alert.addClass('profile-alert-success');
            $icon.removeClass('fa-circle-exclamation').addClass('fa-circle-check');
        } else {
            $alert.addClass('profile-alert-error');
            $icon.removeClass('fa-circle-check').addClass('fa-circle-exclamation');
        }
        
        $alert.find('.alert-message').text(message);
        $alert.slideDown(200);

        // Scroll to top of the page to notice the alert
        $('html, body').animate({ scrollTop: 0 }, 200);

        // Auto-dismiss success alert after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $alert.slideUp(200);
            }, 5000);
        }
    }

    // Escape HTML helper
    function escapeHtml(text) {
        if (!text) return '';
        return text
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Get initials fallback helper
    function getInitials(name) {
        if (!name) return '?';
        return name
            .split(' ')
            .filter(Boolean)
            .map(word => word[0])
            .join('')
            .toUpperCase()
            .substring(0, 2);
    }

    // Fetch and store all users
    function loadUsers() {
        $('#tableLoadingOverlay').addClass('active');

        $.ajax({
            url: 'api/user-accounts-actions.php',
            type: 'POST',
            data: { action: 'fetch_users' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    allUsers = response.data || [];
                    // Render current page (or page 1 if current page exceeds bounds)
                    const totalPages = Math.ceil(allUsers.length / pageSize);
                    if (currentPage > totalPages) {
                        currentPage = totalPages > 0 ? totalPages : 1;
                    }
                    renderUsersPage(currentPage);
                } else {
                    showAlert('error', 'Error fetching users: ' + response.message);
                }
            },
            error: function() {
                showAlert('error', 'Failed to fetch user accounts. Connection error.');
            },
            complete: function() {
                $('#tableLoadingOverlay').removeClass('active');
            }
        });
    }

    // Render a specific page of users
    function renderUsersPage(page) {
        const $tbody = $('#usersTableBody');
        $tbody.empty();

        const totalUsers = allUsers.length;
        if (totalUsers === 0) {
            $tbody.append(`
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <i class="fa-solid fa-users-slash"></i>
                            <p>No user accounts found.</p>
                        </div>
                    </td>
                </tr>
            `);
            $('#paginationControls').hide();
            return;
        }

        const totalPages = Math.ceil(totalUsers / pageSize);
        if (page < 1) page = 1;
        if (page > totalPages) page = totalPages;
        currentPage = page;

        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = Math.min(startIndex + pageSize, totalUsers);
        const pageUsers = allUsers.slice(startIndex, endIndex);

        pageUsers.forEach(function(user) {
            const isSelf = parseInt(user.id) === parseInt(currentUserId);
            const initials = getInitials(user.real_name);
            
            // Avatar section
            let avatarHtml = '';
            if (user.profile_photo_url) {
                avatarHtml = `<img src="${user.profile_photo_url}" alt="Avatar">`;
            } else {
                avatarHtml = `<span>${escapeHtml(initials)}</span>`;
            }

            // Role class mapping
            let roleClass = 'badge-role-user';
            if (user.role === 'admin') {
                roleClass = 'badge-role-admin';
            } else if (user.role === 'manager') {
                roleClass = 'badge-role-manager';
            }

            // Delete button block (disable for self)
            const deleteBtn = isSelf 
                ? `<button class="btn-action-icon btn-action-delete" disabled title="You cannot delete your own account">
                     <i class="fa-solid fa-ban"></i>
                   </button>`
                : `<button class="btn-action-icon btn-action-delete btn-delete-user" data-id="${user.id}" data-name="${escapeHtml(user.real_name)}" title="Delete User">
                     <i class="fa-solid fa-trash-can"></i>
                   </button>`;

            // Action buttons block (Edit + Delete Icons)
            const actionsHtml = `
                <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                    <button class="btn-action-icon btn-action-edit btn-edit-user" 
                            data-id="${user.id}" 
                            data-username="${escapeHtml(user.username)}" 
                            data-name="${escapeHtml(user.real_name)}" 
                            data-dept="${escapeHtml(user.department)}" 
                            data-role="${escapeHtml(user.role)}"
                            title="Edit User">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    ${deleteBtn}
                </div>
            `;

            const rowHtml = `
                <tr>
                    <td>
                        <div class="user-profile-block">
                            <div class="user-avatar-circle">
                                ${avatarHtml}
                            </div>
                            <div class="user-meta-block">
                                <span class="user-display-realname">${escapeHtml(user.real_name)}</span>
                            </div>
                        </div>
                    </td>
                    <td><span class="user-display-username">${escapeHtml(user.username)}</span></td>
                    <td><span class="badge badge-dept">${escapeHtml(user.department || '-')}</span></td>
                    <td><span class="badge ${roleClass}">${escapeHtml(user.role)}</span></td>
                    <td>${actionsHtml}</td>
                </tr>
            `;
            $tbody.append(rowHtml);
        });

        // Setup pagination buttons
        renderPaginationControls(totalPages);
    }

    // Generate pagination controls HTML and bind event listeners
    function renderPaginationControls(totalPages) {
        const $controls = $('#paginationControls');
        $controls.empty();

        const totalUsers = allUsers.length;

        // If pagination is not required, hide the container
        if (totalUsers <= pageSize) {
            $controls.hide();
            return;
        }

        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = Math.min(startIndex + pageSize, totalUsers);

        // 1. Info text display
        const infoHtml = `<div class="pagination-info">Showing <b>${startIndex + 1}</b> to <b>${endIndex}</b> of <b>${totalUsers}</b> users</div>`;
        $controls.append(infoHtml);

        // 2. Buttons container
        const $btnContainer = $('<div class="pagination-buttons"></div>');

        // Prev page button
        const $prevBtn = $(`<button class="pagination-btn" ${currentPage === 1 ? 'disabled' : ''}><i class="fa-solid fa-chevron-left"></i></button>`);
        $prevBtn.on('click', function() {
            if (currentPage > 1) {
                renderUsersPage(currentPage - 1);
            }
        });
        $btnContainer.append($prevBtn);

        // Numeric buttons
        for (let i = 1; i <= totalPages; i++) {
            const $pageBtn = $(`<button class="pagination-btn ${currentPage === i ? 'active' : ''}">${i}</button>`);
            $pageBtn.on('click', function() {
                renderUsersPage(i);
            });
            $btnContainer.append($pageBtn);
        }

        // Next page button
        const $nextBtn = $(`<button class="pagination-btn" ${currentPage === totalPages ? 'disabled' : ''}><i class="fa-solid fa-chevron-right"></i></button>`);
        $nextBtn.on('click', function() {
            if (currentPage < totalPages) {
                renderUsersPage(currentPage + 1);
            }
        });
        $btnContainer.append($nextBtn);

        $controls.append($btnContainer);
        $controls.show();
    }

    // Submit Add User Form
    $('#addUserForm').on('submit', function(e) {
        e.preventDefault();

        const username = $('#modalUsername').val().trim();
        const realName = $('#modalRealName').val().trim();
        const password = $('#modalPassword').val();
        const confirmPassword = $('#modalConfirmPassword').val();
        const department = $('#modalDepartment').val();
        const userType = $('#modalUserType').val();

        // Extra client-side checks
        if (username.length < 3) {
            alert('Username must be at least 3 characters.');
            $('#modalUsername').focus();
            return;
        }
        if (realName.length < 2) {
            alert('Real name must be at least 2 characters.');
            $('#modalRealName').focus();
            return;
        }
        if (password.length < 5) {
            alert('Password must be at least 5 characters.');
            $('#modalPassword').focus();
            return;
        }
        if (password !== confirmPassword) {
            alert('Passwords do not match.');
            $('#modalConfirmPassword').focus();
            return;
        }

        const $submitBtn = $('#btnSubmitAddUser');
        const originalHtml = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Adding...');

        $.ajax({
            url: 'api/user-accounts-actions.php',
            type: 'POST',
            data: {
                action: 'add_user',
                username: username,
                real_name: realName,
                password: password,
                department: department,
                user_type: userType
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#addUserModal').removeClass('active');
                    $('#addUserForm')[0].reset();
                    showAlert('success', 'User ' + realName + ' added successfully!');
                    loadUsers();
                } else {
                    alert('Error adding user: ' + response.message);
                }
            },
            error: function() {
                alert('Connection error occurred while adding user.');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Submit Edit User Form
    $('#editUserForm').on('submit', function(e) {
        e.preventDefault();

        const id = $('#editUserId').val();
        const password = $('#editPassword').val();
        const confirmPassword = $('#editConfirmPassword').val();
        const department = $('#editDepartment').val();
        const userType = $('#editUserType').val();

        // Extra client-side checks
        if (password) {
            if (password.length < 5) {
                alert('New password must be at least 5 characters.');
                $('#editPassword').focus();
                return;
            }
            if (password !== confirmPassword) {
                alert('New passwords do not match.');
                $('#editConfirmPassword').focus();
                return;
            }
        }

        const $submitBtn = $('#btnSubmitEditUser');
        const originalHtml = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...');

        $.ajax({
            url: 'api/user-accounts-actions.php',
            type: 'POST',
            data: {
                action: 'edit_user',
                id: id,
                password: password,
                department: department,
                user_type: userType
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#editUserModal').removeClass('active');
                    $('#editUserForm')[0].reset();
                    showAlert('success', 'User details updated successfully!');
                    loadUsers();
                } else {
                    alert('Error saving user changes: ' + response.message);
                }
            },
            error: function() {
                alert('Connection error occurred while saving user changes.');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Delete User Handler
    $(document).on('click', '.btn-delete-user', function() {
        const userId = $(this).data('id');
        const userName = $(this).data('name');

        if (!confirm('Are you sure you want to delete user account: ' + userName + '? This action is permanent!')) {
            return;
        }

        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Deleting...');

        $.ajax({
            url: 'api/user-accounts-actions.php',
            type: 'POST',
            data: {
                action: 'delete_user',
                id: userId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', 'User account ' + userName + ' deleted successfully.');
                    loadUsers();
                } else {
                    showAlert('error', 'Error deleting user: ' + response.message);
                }
            },
            error: function() {
                showAlert('error', 'Connection error occurred while deleting user.');
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });
});
