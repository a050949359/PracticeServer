export default {
    common: {
        cancel: 'Cancel',
    },
    inviteDialog: {
        title: 'Send Registration Invitation',
        form: {
            nameLabel: 'Invitee Name',
            namePlaceholder: 'Optional',
            emailLabel: 'Invitee Email',
            contextLabel: 'Invitation Type',
            contextPlaceholder: 'Select invitation type',
        },
        actions: {
            submit: 'Send Invitation',
        },
        contexts: {
            userInvitedRegister: 'User Invitation',
            staffInvitedRegister: 'Staff Invitation',
        },
        validation: {
            emailRequired: 'Please enter an email',
            emailInvalid: 'Invalid email format',
            contextRequired: 'Please select an invitation type',
        },
        messages: {
            success: 'Invitation sent',
            failure: 'Failed to send invitation. Please try again later.',
        },
    },
    authDialogs: {
        form: {
            nameLabel: 'Name',
            namePlaceholder: 'Please enter your name',
            passwordLabel: 'Password',
            passwordPlaceholder: 'Please enter your password',
            passwordMinPlaceholder: 'At least 8 characters',
            passwordConfirmationLabel: 'Confirm Password',
            passwordConfirmationPlaceholder: 'Enter your password again',
        },
        actions: {
            login: 'Sign In',
            register: 'Create Account',
        },
        validation: {
            nameRequired: 'Please enter your name',
            nameMin: 'Name must be at least 2 characters',
            emailRequired: 'Please enter an email',
            emailInvalid: 'Invalid email format',
            passwordRequired: 'Please enter a password',
            passwordMin: 'Password must be at least 8 characters',
            passwordConfirmationRequired: 'Please confirm your password',
            passwordMismatch: 'Passwords do not match',
        },
        messages: {
            loginSuccess: 'Login successful',
            invalidCredentials: 'Login failed. Please check your email and password.',
            forbiddenAdminOnly: 'This account does not have admin login permission.',
            forbiddenPublicOnly: 'This account can only sign in from admin.',
            registerSuccess: 'Registration successful. Please sign in with your new account.',
            registerFailure: 'Registration failed. Please try again later.',
        },
    },
    profileDialog: {
        title: 'Profile',
        fields: {
            name: 'Name',
            id: 'ID',
        },
    },
    register: {
        nav: {
            home: 'Home',
        },
        panel: {
            invitationTag: 'Invitation Register',
            invitationTitle: 'Complete Invitation Registration',
            verificationTag: 'Email Verification',
            verificationTitle: 'Email Verification Result',
        },
        verification: {
            verifiedAt: 'Verified at: {value}',
            actions: {
                home: 'Go Home',
                admin: 'Go to Admin',
            },
            codes: {
                invalid_signature: {
                    title: 'Invalid verification link',
                    message: 'This verification link has expired or was modified. Please request a new verification email.',
                },
                user_not_found: {
                    title: 'User not found',
                    message: 'No account could be found for this verification link.',
                },
                invalid_hash: {
                    title: 'Invalid verification link',
                    message: 'The verification payload is invalid. Please request a new verification email.',
                },
                already_verified: {
                    title: 'Email already verified',
                    message: 'This account was already verified. You can sign in directly.',
                },
                verified: {
                    title: 'Email verified',
                    message: 'Your email has been verified successfully. You can now return to the app.',
                },
                default: {
                    title: 'Email verification result',
                    message: 'Please follow the on-screen instructions to continue.',
                },
            },
        },
        invitation: {
            missingToken: 'Missing invitation token. Registration cannot continue.',
            invalid: 'This invitation link is invalid or expired.',
            apiErrors: {
                invitation_not_found: 'This invitation link does not exist or is no longer valid.',
                invitation_already_used: 'This invitation has already been used. Please ask an admin to resend one.',
                invitation_expired: 'This invitation has expired. Please ask an admin to resend one.',
                invitation_email_already_registered: 'This invitation email is already registered. Please sign in directly.',
                default: 'This invitation link is invalid or expired.',
            },
            forEmail: 'You are completing registration for {email}.',
            invitedName: 'Invitation name: {name}',
            unknownName: 'Not provided',
            password: 'Password',
            passwordPlaceholder: 'At least 8 characters',
            passwordConfirmation: 'Confirm password',
            passwordConfirmationPlaceholder: 'Enter the password again',
            submit: 'Complete registration',
            success: 'Invitation registration completed. Redirecting now.',
            failure: 'Registration failed. Please try again later.',
            validation: {
                passwordRequired: 'Please enter a password',
                passwordMin: 'Password must be at least 8 characters',
                passwordConfirmationRequired: 'Please confirm your password',
                passwordMismatch: 'Passwords do not match',
            },
        },
    },
};