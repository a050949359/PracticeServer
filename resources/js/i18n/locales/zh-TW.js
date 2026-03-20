export default {
    common: {
        cancel: '取消',
    },
    inviteDialog: {
        title: '發送註冊邀請',
        form: {
            nameLabel: '受邀者名稱',
            namePlaceholder: '可留空',
            emailLabel: '受邀者 Email',
            contextLabel: '邀請類型',
            contextPlaceholder: '選擇邀請類型',
        },
        actions: {
            submit: '送出邀請',
        },
        contexts: {
            userInvitedRegister: '一般使用者邀請',
            staffInvitedRegister: '員工邀請',
        },
        validation: {
            emailRequired: '請輸入 Email',
            emailInvalid: 'Email 格式不正確',
            contextRequired: '請選擇邀請類型',
        },
        messages: {
            success: '邀請已送出',
            failure: '邀請送出失敗，請稍後再試',
        },
    },
    authDialogs: {
        form: {
            nameLabel: '名稱',
            namePlaceholder: '請輸入名稱',
            passwordLabel: '密碼',
            passwordPlaceholder: '請輸入密碼',
            passwordMinPlaceholder: '至少 8 碼',
            passwordConfirmationLabel: '確認密碼',
            passwordConfirmationPlaceholder: '請再次輸入密碼',
        },
        actions: {
            login: '登入',
            register: '建立帳號',
        },
        validation: {
            nameRequired: '請輸入名稱',
            nameMin: '名稱至少 2 個字',
            emailRequired: '請輸入 Email',
            emailInvalid: 'Email 格式不正確',
            passwordRequired: '請輸入密碼',
            passwordMin: '密碼至少 8 碼',
            passwordConfirmationRequired: '請輸入確認密碼',
            passwordMismatch: '兩次密碼不一致',
        },
        messages: {
            loginSuccess: '登入成功',
            invalidCredentials: '登入失敗，請檢查帳號密碼',
            forbiddenAdminOnly: '此帳號沒有後台登入權限',
            forbiddenPublicOnly: '此帳號僅可從後台登入',
            registerSuccess: '註冊成功，請使用新帳號登入',
            registerFailure: '註冊失敗，請稍後再試',
        },
    },
    profileDialog: {
        title: '個人資料',
        fields: {
            name: '名稱',
            id: 'ID',
        },
    },
    register: {
        nav: {
            home: '回首頁',
        },
        panel: {
            invitationTag: 'Invitation Register',
            invitationTitle: '完成邀請註冊',
            verificationTag: 'Email Verification',
            verificationTitle: 'Email 驗證結果',
        },
        verification: {
            verifiedAt: '驗證時間：{value}',
            actions: {
                home: '回首頁',
                admin: '前往後台',
            },
            codes: {
                invalid_signature: {
                    title: '驗證連結無效',
                    message: '這封驗證信已過期，或連結已被修改。請重新寄送驗證信。',
                },
                user_not_found: {
                    title: '找不到使用者',
                    message: '這個驗證連結找不到對應帳號。',
                },
                invalid_hash: {
                    title: '驗證連結無效',
                    message: '驗證資料不正確，請重新寄送驗證信。',
                },
                already_verified: {
                    title: 'Email 已完成驗證',
                    message: '這個帳號先前已驗證完成，可以直接登入。',
                },
                verified: {
                    title: 'Email 驗證成功',
                    message: '你的 Email 已完成驗證，現在可以回到系統登入。',
                },
                default: {
                    title: 'Email 驗證結果',
                    message: '請依畫面指示完成後續操作。',
                },
            },
        },
        invitation: {
            missingToken: '缺少邀請 token，無法完成註冊。',
            invalid: '邀請連結無效或已過期。',
            apiErrors: {
                invitation_not_found: '邀請連結不存在或已失效。',
                invitation_already_used: '此邀請已被使用，請聯繫管理員重新發送。',
                invitation_expired: '此邀請已過期，請聯繫管理員重新發送。',
                invitation_email_already_registered: '此邀請 Email 已完成註冊，請直接登入。',
                default: '邀請連結無效或已過期。',
            },
            forEmail: '你正以 {email} 完成邀請註冊。',
            invitedName: '邀請名稱：{name}',
            unknownName: '未提供',
            password: '密碼',
            passwordPlaceholder: '至少 8 碼',
            passwordConfirmation: '確認密碼',
            passwordConfirmationPlaceholder: '請再次輸入密碼',
            submit: '完成註冊',
            success: '邀請註冊完成，正在為你導向首頁',
            failure: '完成註冊失敗，請稍後再試。',
            validation: {
                passwordRequired: '請輸入密碼',
                passwordMin: '密碼至少 8 碼',
                passwordConfirmationRequired: '請輸入確認密碼',
                passwordMismatch: '兩次密碼不一致',
            },
        },
    },
};