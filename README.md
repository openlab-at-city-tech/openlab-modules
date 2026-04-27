# OpenLab Module Builder

OpenLab Module Builder is a WordPress plugin that allows you to organize WP content into "modules", which are reusable and clonable collections of sequenced content. An integrated completion system allows notifications to be sent when a module is "completed" - all embedded WeBWorK items are successfully filled out, etc.

This plugin was developed for the (City Tech OpenLab)[https://openlab.citytech.cuny.edu/].

## Development

The public source repository for this plugin is available at https://github.com/openlab-at-city-tech/openlab-module-builder

The files in `build/` are generated production assets. Their human-readable JavaScript and CSS sources live in `assets/src/`.

Bundled JavaScript dependencies are declared in `package.json` and `package-lock.json`. PHP dependencies are declared in `composer.json`.

To build the production assets from source:

1. `npm install`
2. `composer install`
3. `npm run build`
