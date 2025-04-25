// webpack.config.js
const defaultConfig = require( "@wordpress/scripts/config/webpack.config" );

module.exports = {
  ...defaultConfig,
  module: {
    ...defaultConfig.module,
    rules: [
      ...defaultConfig.module.rules,
      {
        test: /\.jsx?$/,
        exclude: /node_modules\/(?!gutenberg-post-picker)/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-react'],
          },
        },
      },
    ],
  },
  externals: ( ( { name }, callback ) => {
    if ( name === 'admin' ) {
      // Admin bundle: react and react-dom must be externals
      callback( null, {
        react: 'React',
        'react-dom': 'ReactDOM',
      } );
    } else {
      // Frontend (and other) bundles: use default externals
      callback( null, defaultConfig.externals );
    }
  } ),
};
