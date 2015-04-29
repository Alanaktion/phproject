/**
 * Phproject Asset Compiler
 *
 * @author Alan Hardman <alan@phpizza.com>
 */

var gulp = require('gulp'),
	concat = require('gulp-concat'),
	less = require('gulp-less'),
	minifycss = require('gulp-minify-css'),
	notify = require('gulp-notify'),
	uglify = require('gulp-uglify');

gulp.task('css', function() {
	return gulp
		.src(['src/less/themes/*.less'])
		.pipe(less().on('error', notify.onError(function(error) {
			return 'Error compiling LESS: ' + error.message;
		})))
		.on('error', function() {
			this.emit('end');
		})
		// .pipe(minifycss())
		.pipe(gulp.dest('theme-css/'));
});

gulp.task('js', function() {
    var scripts = [
    	'src/js/jquery-1.7.2.min.js',
    	'src/js/jquery-ui-dragsort.min.js',
    	'src/js/jquery.ui.touch-punch.min.js',
    	'src/js/bootstrap.min.js',
    	'src/js/bootstrap-datepicker.js',
    	'src/js/modernizr.custom.js',
    	'src/js/intercom.min.js',
    	'src/js/stupidtable.min.js',
    	'src/js/global.js'
    ];

    return gulp
        .src(scripts)
        .pipe(concat('script.js'))
        .pipe(uglify())
        .pipe(gulp.dest('js'))
        .pipe(notify({
            message: 'Successfully compiled JavaScript'
        }));
});

gulp.task('default', ['css', 'js', 'watch']);

gulp.task('watch', function() {
	gulp.watch(['src/less/**/*.less', 'src/less/**/*.css'], ['css']);
	gulp.watch('src/js/**/*.js', ['js']);
});
