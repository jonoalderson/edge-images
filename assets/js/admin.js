/**
 * Edge Images settings: show provider-specific fields.
 */
(function () {
	'use strict';

	function selectedProvider(root) {
		var select = root.querySelector('.edge-images-provider-select');
		return select ? select.value : 'none';
	}

	function syncConditionalFields(root) {
		var provider = selectedProvider(root);
		var rows = root.querySelectorAll('tr.edge-images-conditional');

		rows.forEach(function (row) {
			var show = row.classList.contains('edge-images-show-' + provider);
			if (show) {
				row.removeAttribute('hidden');
			} else {
				row.setAttribute('hidden', 'hidden');
			}
		});
	}

	function init() {
		var root = document.querySelector('[data-edge-images-admin]');
		if (!root) {
			return;
		}

		syncConditionalFields(root);

		var select = root.querySelector('.edge-images-provider-select');
		if (select) {
			select.addEventListener('change', function () {
				syncConditionalFields(root);
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
