const { merge } = require('webpack-merge');
const path = require("path");
const webpackConfig = require('./webpack.config');
const CopyPlugin = require("copy-webpack-plugin");

module.exports = merge(webpackConfig, {
  mode: 'development',
  devtool: false,
  plugins: [
    new CopyPlugin({
      patterns: [
        {
          from: path.resolve(__dirname, "node_modules/@popperjs/core/dist/umd/popper.js"),
          to: path.resolve(__dirname, "js/popperjs"),
          context: "node_modules/@popperjs/core/dist/",
        },
        {
          from: path.resolve(__dirname, "node_modules/bootstrap/js/dist/*.js"),
          to: path.resolve(__dirname, "js/bootstrap"),
          context: "node_modules/bootstrap/js/dist/",
        },
        {
          from: path.resolve(__dirname, "node_modules/bootstrap/js/dist/dom/*.js"),
          to: path.resolve(__dirname, "js/bootstrap/dom"),
          context: "node_modules/bootstrap/js/dist/dom",
        },
        {
          from: path.resolve(__dirname, "node_modules/bootstrap/js/dist/util/*.js"),
          to: path.resolve(__dirname, "js/bootstrap/util"),
          context: "node_modules/bootstrap/js/dist/util",
        }
      ],
    }),
  ],
});
