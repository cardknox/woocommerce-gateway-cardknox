// blocks/src/components/ApplePayButton.js
import { useEffect } from '@wordpress/element';

const ApplePayButton = ({ isEditor }) => {
	// Render the same markup your classic gateway outputs,
	// so your existing minified script can hook into it.
	useEffect(() => {
		// Re-trigger WC's updated_checkout so your classic JS initializes inside Blocks
		if (window.jQuery && !isEditor) {
			window.jQuery(document.body).trigger('updated_checkout');
		}
	}, [isEditor]);

	return (
		<>
			<div id="ap-container" className="ap hidden" style={{ minHeight: '55px' }} />
			<br />
			<div className="messages">
				<div className="message message-error error applepay-error" style={{ display: 'none' }} />
			</div>
		</>
	);
};

export default ApplePayButton;