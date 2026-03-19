export default {
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