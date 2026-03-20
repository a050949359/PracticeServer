const MIN_PASSWORD_LENGTH = 12;

const hasLowercase = (value) => /[a-z]/.test(value);
const hasUppercase = (value) => /[A-Z]/.test(value);
const hasNumber = (value) => /\d/.test(value);
const hasSymbol = (value) => /[^A-Za-z0-9]/.test(value);

export const validatePasswordPolicy = (password) => {
    if (password.length < MIN_PASSWORD_LENGTH) {
        return 'min_length';
    }

    if (!(hasLowercase(password) && hasUppercase(password))) {
        return 'mixed_case';
    }

    if (!hasNumber(password)) {
        return 'numbers';
    }

    if (!hasSymbol(password)) {
        return 'symbols';
    }

    return null;
};
