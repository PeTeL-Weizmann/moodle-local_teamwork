"use strict";

var gulp = require("gulp");
var sass = require("gulp-sass");
var rename = require("gulp-rename");
var plumber = require("gulp-plumber");
var postcss = require("gulp-postcss");
var autoprefixer = require("autoprefixer");
var mqpacker = require("css-mqpacker");
var sequence = require("gulp-sequence");
var del = require("del");
var shell = require('gulp-shell');
var uglify = require('gulp-uglify-es').default;
var gutil = require('gulp-util');
var pipeline = require('readable-stream').pipeline;

// Sharingactivities - style.
gulp.task("clean", function () {
    return del("style.css");
});

gulp.task("style", function () {
    gulp.src("scss/style.scss")
        .pipe(plumber())
        .pipe(sass())
        .pipe(postcss([
            autoprefixer({ browsers: ["last 2 versions"] }),
            mqpacker({ sort: true })
        ]))
        .pipe(rename("styles.css"))
        .pipe(gulp.dest('.'));

});

gulp.task('purge_caches', shell.task('cd ../../admin/cli && php purge_caches.php'))

// Minify js.
gulp.task('clean_js', function () {
    return del('amd/build/*.js');
});

gulp.task('compress', function () {
    return pipeline(
        gulp.src('amd/src/*.js'),
        uglify()
            .on('error', function (err) { gutil.log(gutil.colors.red('[Error]'), err.toString()); }),
        rename({ suffix: '.min' }),
        gulp.dest('amd/build')
    );
});

gulp.task('minjs', function (cb) {
    sequence('clean_js', 'compress', cb);
});

gulp.watch("scss/**/*.{scss,sass}", ["style", "purge_caches"]);
gulp.watch("amd/src/*.js", ["minjs"]);

gulp.task("build", function (cb) {
    sequence(
        "clean",
        "style",
        "minjs",
        "purge_caches",
        cb
    );
});
