/**
 *  Welcome to your gulpfile!
 *  The gulp tasks are splitted in several files in the gulp directory
 *  because putting all here was really too long
 */

'use strict';

var gulp = require('gulp');
var browserSync = require('browser-sync');
var print = require("gulp-print");

var $ = require('gulp-load-plugins')({
  pattern: ['gulp-*', 'del', 'main-bower-files']
});

var paths = {
  dirs: {
    src: 'src',
    serve: 'serve',
    composer: 'vendor'
  }
}

var phpOptions = {
  'serve': {
    'base': paths.dirs.serve,
    'keepalive': true
  }
}

gulp.task('clean', function() {
  return $.del(
  [
    paths.dirs.serve
  ]);
});

gulp.task('phpvendor', function() {
  return gulp.src(paths.dirs.composer + '/**/*')
    .pipe(gulp.dest(paths.dirs.serve + '/vendor/'));
});
gulp.task('all', function() {
  return gulp.src([paths.dirs.src + '/**/*', paths.dirs.src + '/.env'])
    .pipe(gulp.dest( paths.dirs.serve ));
});

gulp.task('phpServer', function() {
  $.connectPhp.server(phpOptions.serve, function() {
    browserSync({
      proxy: 'localhost:8000'
    });
  });
});
gulp.task('reload', function(event) {
  browserSync.reload(event.path);
});

gulp.task('watch', function() {
  gulp.watch(paths.dirs.src, gulp.series('all', 'reload'));
  return;
});
gulp.task('server', gulp.parallel('phpServer', 'watch'));

gulp.task('serve', gulp.series('clean', 'phpvendor', 'all', 'server'));