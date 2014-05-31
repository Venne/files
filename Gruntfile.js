module.exports = function(grunt) {

	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),

		copy: {
			main: {
				files: [
					{expand: true, cwd: 'bower_components/blueimp-file-upload/js/', src: 'jquery.iframe-transport.js', dest: 'Resources/public/vendor/blueimp-file-upload/'},
					{expand: true, cwd: 'bower_components/blueimp-file-upload/js/', src: 'jquery.fileupload.js', dest: 'Resources/public/vendor/blueimp-file-upload/'},
				]
			}
		},

	  	uglify: {
	  		options: {
		        beautify: true
		    },
			js: {
				files: {
					'Resources/public/js/application.min.js': ['Resources/public/js/*.js', '!js/*.min.js']
				}
			}
		},

		sass: {
			dist: {
				options: {
					style: 'expanded'
				},
				files: {
					'Resources/public/css/application.css': 'Resources/public/css/application.scss'
				}
			}
		},

		cssmin: {
			combine: {
				files: {
					'Resources/public/css/application.min.css': ['Resources/public/css/application.css']
				}
			},
			minify: {
				expand: true,
				cwd: 'css/',
				src: ['index.css', 'legacy_ie.css'],
				dest: 'css/'
			}
		},

		imagemin: {
			dynamic: {
				options: {
					optimizationLevel: 3
				},
				files: [{
					expand: true,
					cwd: 'Resources/public/',
					src: ['**/*.{png,jpg,gif}'],
					dest: 'Resources/public/'
				}]
			}
		},
		
		autoprefixer: {
		  dist: {
		    options: {
		      browsers: ['last 1 version', '> 1%', 'ie 8', 'ie 7']
		    },
		    files: {
		      'css/index.css': ['css/index.css']
		    }
		  }
		},

		watch: {
			css: {
				files: ['Resources/public/*/*.scss'],
				tasks: ['sass', 'autoprefixer', 'cssmin']
			},

			imagemin: {
				files: [
					'Resources/public/*/*.jpg',
					'Resources/public/*/*.jpeg',
					'Resources/public/*/*.png',
					'Resources/public/*/*.gif'
				],
				tasks: ['imagemin']
			}
		}
	});

	grunt.loadNpmTasks('grunt-contrib-copy');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-sass');
	grunt.loadNpmTasks('grunt-contrib-cssmin');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-contrib-imagemin');
	grunt.loadNpmTasks('grunt-autoprefixer');

	grunt.registerTask('default', ['copy', 'sass', 'uglify', 'autoprefixer', 'cssmin', 'imagemin', 'watch']);
};
