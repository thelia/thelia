module.exports = function (grunt) {

    require('load-grunt-tasks')(grunt);

    grunt.initConfig({
        jshint: {
            all: [
                'assets/src/js/*.js',
                '!assets/src/js/vendors/*.js'
            ]
        },
        uglify: {
            all: {
                files: {
                    'assets/dist/js/thelia.min.js': 'assets/src/js/thelia.js'
                }
            }
        },
        less: {
            all: {
                options: {
                    paths: 'assets/src/css'
                },
                files: {
                    'assets/src/css/thelia.css': 'assets/src/less/thelia.less',
                    'assets/dist/css/thelia.min.css': 'assets/src/less/thelia.less'
                }
            }
        },
        autoprefixer: {
            options: {
                browsers: ['last 2 versions', 'ie 8', 'ie 9']
            },
            all: {
                src: 'assets/src/css/thelia.css'
            }
        },
        cssmin: {
            target: {
                files: {
                    'assets/dist/css/thelia.min.css': 'assets/src/css/thelia.css'
                }
            }
        },
        imagemin: {
            all:{
                files: [{
                    expand: true,
                    cwd: 'assets/src/img',
                    src: ['**/*.{png,jpg,gif,svg,ico}'],
                    dest: 'assets/dist/img'
                }]
            }
        },
        copy: {
            js: {
                files: [
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/src/js/vendors',
                        src: 'bower_components/html5shiv/dist/html5shiv.js'
                    },
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/dist/js/vendors',
                        src: 'bower_components/html5shiv/dist/html5shiv.min.js'
                    },
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/src/js/vendors',
                        src: 'bower_components/respond/src/respond.js'
                    },
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/dist/js/vendors',
                        src: 'bower_components/respond/dest/respond.min.js'
                    },
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/src/js/vendors',
                        src: 'bower_components/jquery/dist/jquery.js'
                    },
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/dist/js/vendors',
                        src: 'bower_components/jquery/dist/jquery.min.js'
                    },
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/src/js/vendors',
                        src: 'bower_components/bootstrap/dist/js/bootstrap.js'
                    },
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/dist/js/vendors',
                        src: 'bower_components/bootstrap/dist/js/bootstrap.min.js'
                    },
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/src/js/vendors',
                        src: 'bower_components/bootbox/bootbox.js'
                    },
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/dist/js/vendors',
                        src: 'bower_components/bootbox/bootbox.js'
                    }
                ]
            },
            fonts: {
                files: [
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/src/fonts/bootstrap',
                        src: ['bower_components/bootstrap/fonts/*.*']
                    },
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/dist/fonts/bootstrap',
                        src: ['bower_components/bootstrap/fonts/*.*']
                    },
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/src/fonts/fontawesome',
                        src: ['bower_components/fontawesome/fonts/*.*']
                    },
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/dist/fonts/fontawesome',
                        src: ['bower_components/fontawesome/fonts/*.*']
                    }
                ]
            },
            less: {
                files: [
                    {
                        expand: true,
                        flatten: false,
                        dest: 'assets/src/less/vendors/bootstrap',
                        cwd: 'bower_components/bootstrap/less',
                        src:['**/*.less']
                    },
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/src/less/vendors/fontawesome',
                        src: ['bower_components/fontawesome/less/*.less']
                    }
                ]
            },
            images: {
                files: [
                    {
                        expand: true,
                        flatten: true,
                        dest: 'assets/dist/img',
                        src:['assets/src/img/**/*.{png,jpg,gif,svg,ico}']
                    }
                ]
            }
        },
        csscount: {
            dev: {
                src: [
                    'assets/src/css/thelia.css',
                    'assets/dist/css/thelia.min.css'
                ]
            }
        },
        watch: {
            html: {
                files: ['*.html', '*.tpl'],
                options: {
                    spawn: false,
                    livereload: true
                }
            },
            less: {
                files: ['assets/src/less/**/*.less'],
                tasks: ['less'],
                options: {
                    spawn: false,
                    livereload: true
                }
            },
            cssmin: {
                files: ['assets/src/css/thelia.css'],
                tasks: ['autoprefixer', 'cssmin'],
                options: {
                    spawn: false,
                    livereload: true
                }
            },
            js: {
                files: ['assets/src/js/*.js'],
                tasks: ['jshint', 'uglify'],
                options: {
                    spawn: false,
                    livereload: true
                }
            },
            img:{
                files: ['assets/src/img/**'],
                tasks: ['imagemin'],
                options: {
                    spawn: false,
                    livereload: true
                }
            }
        }
    });

    grunt.registerTask('default', ['copy', 'jshint', 'uglify', 'less', 'autoprefixer', 'cssmin', 'imagemin']);

}