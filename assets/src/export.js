/* global openlabModulesExport, wp */

(function(){
	const { i18n } = wp
	const { __, sprintf } = i18n

	const moduleSelector = document.getElementById('module-select')
	const moduleAcknowledgementsText = document.getElementById('acknowledgements-text')

	const getModuleAttributionText = (moduleIdRaw) => {
		const moduleId = parseInt(moduleIdRaw)
		const module = openlabModulesExport.modules.find(m => m.id === moduleId)
		if ( ! module ) {
			return ''
		}

		return sprintf(
			// Translators: %1$s is a URL, %2$s is a module name, %3$s is an author name.
			__( 'This module is based on <a href="%1$s">%2$s</a> by %3$s.', 'openlab-modules' ),
			module.url,
			module.title,
			module.author_name
		);
	}

	moduleSelector.addEventListener('change', (event) => {
		const moduleId = event.target.value
		const attributionText = getModuleAttributionText(moduleId)
		moduleAcknowledgementsText.innerHTML = attributionText
	})
})()
