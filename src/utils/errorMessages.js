/**
 * User-friendly error message translations
 * Converts technical error messages into plain language that users can understand
 */

/**
 * Get user-friendly error message from error object
 * 
 * @param {Error|Object} error - Error object from axios or other sources
 * @param {Function} t - Translation function (this.$t.bind(this) or useTranslate)
 * @returns {string} User-friendly error message
 */
export function getUserFriendlyError(error, t) {
	// Network errors
	if (error.code === 'ERR_NETWORK' || error.code === 'ERR_INTERNET_DISCONNECTED' || error.message?.includes('Network')) {
		return t('arbeitszeitcheck',
			'Cannot connect to the server. Please check your internet connection and try again.')
	}

	// Timeout errors
	if (error.code === 'ECONNABORTED' || error.message?.includes('timeout')) {
		return t('arbeitszeitcheck',
			'The request took too long. Please try again. If the problem continues, contact your administrator.')
	}

	// Server errors (500)
	if (error.response?.status === 500) {
		return t('arbeitszeitcheck',
			'Something went wrong on our side. Please try again in a moment. If the problem persists, contact your administrator.')
	}

	// Not found (404)
	if (error.response?.status === 404) {
		return t('arbeitszeitcheck',
			'The requested information could not be found. Please refresh the page and try again.')
	}

	// Permission errors (403)
	if (error.response?.status === 403) {
		return t('arbeitszeitcheck',
			'You do not have permission to perform this action. Please contact your administrator if you believe this is an error.')
	}

	// Unauthorized (401)
	if (error.response?.status === 401) {
		return t('arbeitszeitcheck',
			'Your session has expired. Please refresh the page and log in again.')
	}

	// Validation errors (400)
	if (error.response?.status === 400) {
		const serverMessage = error.response?.data?.error || error.message
		return translateServerError(serverMessage, t)
	}

	// Default: Generic but helpful
	return t('arbeitszeitcheck',
		'An error occurred. Please try again. If the problem continues, contact your administrator.')
}

/**
 * Translate server error messages to user-friendly text
 * 
 * @param {string} serverMessage - Error message from server
 * @param {Function} t - Translation function
 * @returns {string} User-friendly error message
 */
function translateServerError(serverMessage, t) {
	if (!serverMessage || typeof serverMessage !== 'string') {
		return t('arbeitszeitcheck',
			'An error occurred. Please check your input and try again.')
	}

	const errorMap = {
		'invalid_date_format': t('arbeitszeitcheck',
			'Please enter the date in the format dd.mm.yyyy (for example: 15.03.2024)'),
		'break_required': t('arbeitszeitcheck',
			'You must take a break before clocking out. According to German labor law, you need to take a break after working 6 hours.'),
		'insufficient_rest_period': t('arbeitszeitcheck',
			'You cannot clock in yet. German labor law requires at least 11 hours of rest between shifts.'),
		'daily_hours_limit_exceeded': t('arbeitszeitcheck',
			'You have exceeded the maximum daily working hours. Please take a break or clock out.'),
		'weekly_hours_limit_exceeded': t('arbeitszeitcheck',
			'You have exceeded the maximum weekly working hours. Please reduce your working hours this week.'),
		'missing_break': t('arbeitszeitcheck',
			'You need to take a break. After 6 hours of work, you must take at least 30 minutes of break time.'),
		'invalid_time_entry': t('arbeitszeitcheck',
			'The time entry is invalid. Please check the start and end times.'),
		'overlapping_time_entry': t('arbeitszeitcheck',
			'This time entry overlaps with another entry. Please adjust the times.'),
		'future_time_entry': t('arbeitszeitcheck',
			'Time entries cannot be in the future. Please enter a valid date and time.'),
		'invalid_absence_request': t('arbeitszeitcheck',
			'The absence request is invalid. Please check the dates and reason.'),
		'overlapping_absence': t('arbeitszeitcheck',
			'This absence request overlaps with another request. Please adjust the dates.'),
		'user_not_found': t('arbeitszeitcheck',
			'User not found. Please contact your administrator.'),
		'working_time_model_not_found': t('arbeitszeitcheck',
			'Working time model not found. Please contact your administrator.'),
		'validation_failed': t('arbeitszeitcheck',
			'Please check your input. Some fields are missing or invalid.'),
		'permission_denied': t('arbeitszeitcheck',
			'You do not have permission to perform this action. Please contact your administrator.'),
		'rate_limit_exceeded': t('arbeitszeitcheck',
			'Too many requests. Please wait a moment and try again.'),
		'service_unavailable': t('arbeitszeitcheck',
			'The service is temporarily unavailable. Please try again later.'),
	}

	// Check for exact match
	if (errorMap[serverMessage]) {
		return errorMap[serverMessage]
	}

	// Check for partial matches (case-insensitive)
	const lowerMessage = serverMessage.toLowerCase()
	for (const [key, value] of Object.entries(errorMap)) {
		if (lowerMessage.includes(key.toLowerCase())) {
			return value
		}
	}

	// If server message looks user-friendly already, return it
	if (serverMessage.length < 200 && !serverMessage.includes('Exception') && !serverMessage.includes('Error:')) {
		return serverMessage
	}

	// Default fallback
	return t('arbeitszeitcheck',
		'An error occurred. Please try again. If the problem continues, contact your administrator.')
}
