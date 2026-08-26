# OpenLab Module Builder

OpenLab Module Builder allows you to create, organize, and display modular content on your WordPress site.

This plugin was developed for the [City Tech OpenLab](https://openlab.citytech.cuny.edu/) and is maintained by the OpenLab team.

See [readme.txt](readme.txt) for more background and information about the plugin's functionality.

## Development

The public source repository for this plugin is available at https://github.com/openlab-at-city-tech/openlab-module-builder

The files in `build/` are generated production assets. Their human-readable JavaScript and CSS sources live in `assets/src/`.

Bundled JavaScript dependencies are declared in `package.json` and `package-lock.json`. PHP dependencies are declared in `composer.json`.

To build the production assets from source:

1. `npm install`
2. `composer install`
3. `npm run build`

## Contributing

Contributions to the project are welcome. Please see the [CONTRIBUTING.md](CONTRIBUTING.md) file for more information on how to contribute.

