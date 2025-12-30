const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const webpack = require('webpack')

webpackConfig.entry = {
	'arbeitszeitcheck-main': path.join(__dirname, 'src', 'main.js'),
	'admin-settings': path.join(__dirname, 'src', 'admin.js'),
	'settings': path.join(__dirname, 'src', 'settings.js'),
	'compliance-dashboard': path.join(__dirname, 'src', 'compliance-dashboard.js'),
	'compliance-violations': path.join(__dirname, 'src', 'compliance-violations.js'),
	'compliance-reports': path.join(__dirname, 'src', 'compliance-reports.js'),
	'manager-dashboard': path.join(__dirname, 'src', 'manager-dashboard.js'),
	'admin-dashboard': path.join(__dirname, 'src', 'admin-dashboard.js'),
	'admin-users': path.join(__dirname, 'src', 'admin-users.js'),
	'working-time-models': path.join(__dirname, 'src', 'working-time-models.js'),
	'audit-log-viewer': path.join(__dirname, 'src', 'audit-log-viewer.js')
}

webpackConfig.output = {
	path: path.resolve(__dirname, 'js'),
	filename: '[name].js',
	chunkFilename: '[name].js',
	// CSS files will be extracted to css/ directory by MiniCssExtractPlugin
	assetModuleFilename: '../css/[name][ext]'
}

// CRITICAL: Configure Vue for @nextcloud/vue v9+ and Vue 3.4+
// v9+ requires the full Vue build with compiler APIs (createApp, openBlock, mergeModels, etc.)
// Vue 3.4+ includes mergeModels in the bundler build
// CSP COMPLIANCE: Templates are compiled at build time via vue-loader (no runtime template compilation)
// This ensures no 'unsafe-eval' is needed - all templates are pre-compiled to render functions
if (webpackConfig.resolve) {
	webpackConfig.resolve.alias = webpackConfig.resolve.alias || {}
	// Use the full Vue bundler build for v9+ compatibility (includes all internal utilities)
	webpackConfig.resolve.alias['vue$'] = 'vue/dist/vue.esm-bundler.js'
} else {
	webpackConfig.resolve = {
		alias: {
			'vue$': 'vue/dist/vue.esm-bundler.js'
		}
	}
}

// Ensure Vue is not externalized - it needs to be bundled with all internals
if (webpackConfig.externals) {
	// Remove vue from externals if present
	if (Array.isArray(webpackConfig.externals)) {
		webpackConfig.externals = webpackConfig.externals.filter(
			external => external !== 'vue' && (typeof external !== 'object' || !external.vue)
		)
	} else if (typeof webpackConfig.externals === 'object') {
		delete webpackConfig.externals.vue
	}
}

// Optimize for memory usage - disable code splitting and minimize parallel processing
webpackConfig.optimization = webpackConfig.optimization || {}
webpackConfig.optimization.splitChunks = false
webpackConfig.optimization.runtimeChunk = false
// Source maps - ALWAYS CSP-compatible (no eval, no unsafe-eval)
// For production: no source maps (saves memory, size, and ensures CSP compliance)
// For development: use 'source-map' (CSP-compatible, no eval) - NEVER use 'eval-*'
// CRITICAL: Nextcloud's CSP does not allow 'unsafe-eval', so we must never use eval-based source maps
if (process.env.NODE_ENV === 'production' || process.env.DOCKER_BUILD) {
	// Production builds: no source maps for maximum CSP compliance
	webpackConfig.devtool = false
} else {
	// Development: use 'source-map' (CSP-compatible, no eval)
	// NEVER use 'eval-cheap-module-source-map' or any 'eval-*' variant
	webpackConfig.devtool = 'source-map'
}
// Reduce memory usage during build
webpackConfig.parallelism = 1
// Enable incremental builds (cache) for faster rebuilds
// Use filesystem cache in Docker, memory cache locally
webpackConfig.cache = process.env.DOCKER_BUILD ? {
	type: 'filesystem',
	cacheDirectory: path.resolve(__dirname, '.webpack-cache'),
	buildDependencies: {
		config: [__filename]
	}
} : true
// Additional memory optimizations
webpackConfig.performance = {
	hints: false // Disable performance hints to save memory
}
// Reduce stats output to save memory
webpackConfig.stats = {
	preset: 'errors-only',
	modules: false,
	chunks: false,
	chunkModules: false,
	assets: false
}

// CRITICAL: Define Vue feature flags BEFORE any other plugins
// This must be done early to ensure they're available during compilation
if (!webpackConfig.plugins) {
	webpackConfig.plugins = []
}

// Remove any existing DefinePlugin that might conflict
webpackConfig.plugins = webpackConfig.plugins.filter(
	plugin => !(plugin && plugin.constructor && plugin.constructor.name === 'DefinePlugin')
)

// Add our DefinePlugin with Vue feature flags and app info
// @nextcloud/vue v9+ supports both runtime (setAppName/setAppVersion) and build-time (DefinePlugin) approaches
// Using build-time replacement ensures it works even if runtime functions aren't available
webpackConfig.plugins.unshift(
	new webpack.DefinePlugin({
		__VUE_OPTIONS_API__: JSON.stringify(true),
		__VUE_PROD_DEVTOOLS__: JSON.stringify(false),
		__VUE_PROD_HYDRATION_MISMATCH_DETAILS__: JSON.stringify(false),
		// Build-time replacement for appName and appVersion (works with both v8 and v9)
		appName: JSON.stringify('arbeitszeitcheck'),
		appVersion: JSON.stringify('1.0.0')
	})
)

module.exports = webpackConfig
