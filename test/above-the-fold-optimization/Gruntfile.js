/* global module:false */
module.exports = function(grunt) {

	// Project configuration
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),
		meta: {
			banner: ''
				//'/*!\n' +
				//' * Above the fold Optimization <%= pkg.version %>\n' +
				//' * @author info@optimalisatie.nl\n' +
				//' */'
		},

		uglify: {
			options: {
				banner: '<%= meta.banner %>\n'
			},
			build: {
				files: {

					// Above The Fold Javascript Controller
					'public/js/abovethefold.min.js' : [
						'public/js/src/abovethefold.js',
						'public/js/src/abovethefold.loadcss.js'
					],

					// Enhanced loadCSS
					'public/js/abovethefold-loadcss-enhanced.min.js' : [
						'public/js/src/abovethefold.loadcss-modified.js'
					],

					// Original loadCSS
					'public/js/abovethefold-loadcss.min.js' : [
						'bower_components/loadcss/loadCSS.js'
					]
				}
			}
		}
	});

	// Load Dependencies
	require('matchdep').filterDev('grunt-*').forEach(grunt.loadNpmTasks);

    grunt.registerTask( 'default', [ 'uglify' ] );
};
