export class ApiError extends Error {
    constructor(message, status, violations = [], code = null) {
        super(message);
        this.status = status;
        this.violations = violations;
        this.code = code;
    }

    get fieldErrors() {
        return Object.fromEntries(this.violations.map((v) => [v.propertyPath, v.title ?? v.message]));
    }
}
