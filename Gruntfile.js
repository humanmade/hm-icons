module.exports = function ( grunt ) {

	'use strict';

	// Project configuration
	grunt.initConfig( {

		pkg: grunt.file.readJSON( 'package.json' ),

		grunticon: {
			fontAwesome: {
				files: [ {
					expand: true,
					src: [ 'black/svg/*.svg' ],
					dest: "icons"
				} ],
				options: {
					compressPNG: true
				}
			}
		}

	} );

	grunt.loadNpmTasks( 'grunt-grunticon' );

	grunt.registerTask( 'default', [ 'grunticon:fontAwesome' ] );

}