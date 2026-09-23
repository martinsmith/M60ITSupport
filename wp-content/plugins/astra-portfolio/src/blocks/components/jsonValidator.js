/**
 *
 * @param {string} json_ JSON stringify data.
 * @return {Object} Return json object.
 */
const jsonValidator = ( json_ ) => {
	try {
		JSON.parse( json_ );
	} catch ( e ) {
		return false;
	}
	return JSON.parse( json_ );
};
export default jsonValidator;
