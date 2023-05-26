import apiFetch from '@wordpress/api-fetch'
import { registerStore } from '@wordpress/data'

const DEFAULT_STATE = {
	modulePagesByModuleId: {},
	pageModulesByPageId: {}
}

const STORE_NAME = 'openlab-modules'

const actions = {
	fetchFromAPI( path ) {
		return {
			type: 'FETCH_FROM_API',
			path
		}
	},

	setPageModules( pageId, pageModules ) {
		return {
			type: 'SET_PAGE_MODULES',
			pageId,
			pageModules
		}
	},

	setModulePages( moduleId, modulePages ) {
		return {
			type: 'SET_MODULE_PAGES',
			moduleId,
			modulePages
		}
	},
}

const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_MODULE_PAGES' :
			return {
				...state,
				modulePagesByModuleId: {
					...state.modulePagesByModuleId,
					[ action.moduleId ]: action.modulePages
				}
			}

		case 'SET_PAGE_MODULES' :
			return {
				...state,
				pageModulesByPageId: {
					...state.pageModulesByPageId,
					[ action.pageId ]: action.pageModules
				}
			}

		default :
			return state
	}
}

const controls = {
	FETCH_FROM_API( action ) {
		return apiFetch( { path: action.path } )
	},
}

const selectors = {
	getModulePages( state, moduleId ) {
		const { modulePagesByModuleId } = state
		const modulePages = modulePagesByModuleId[ moduleId ]

		return modulePages
	},
	getPageModules( state, pageId ) {
		const { pageModulesByPageId } = state
		const pageModules = pageModulesByPageId[ pageId ]

		return pageModules
	},
}

const resolvers = {
	*getModulePages( moduleId ) {
		console.trace()
		const path = '/openlab-modules/v1/module-pages/' + moduleId
		const modulePages = yield actions.fetchFromAPI( path )
		return actions.setModulePages( moduleId, modulePages )
	},
	*getPageModules( pageId ) {
		const path = '/openlab-modules/v1/page-modules/' + pageId
		const pageModules = yield actions.fetchFromAPI( path )
		return actions.setPageModules( pageId, pageModules )
	}
}

const storeConfig = {
	actions,
	reducer,
	controls,
	selectors,
	resolvers
}

registerStore( STORE_NAME, storeConfig )

export { STORE_NAME }
