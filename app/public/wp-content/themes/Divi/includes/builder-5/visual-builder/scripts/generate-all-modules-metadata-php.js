const fs        = require('fs');
const path      = require('path');
const { glob }  = require('glob');
const jsPhpData = require('js-php-data');


/**
 * Writes JavaScript object to be converted to PHP array into a PHP file in the server directory.
 *
 * This function takes JavaScript object data, sorts it alphabetically by keys, converts it to PHP array
 * using the js-php-data library, and writes it to a PHP file.
 *
 * @param {object} data The JavaScript object to be converted to PHP array.
 * @param {string} fileName The name of the PHP file to generate (e.g., '_all_modules_metadata.php').
 */
function writePhpFile(data, fileName) {
  // Sort the data by keys.
  const sortedData = Object.keys(data).sort().reduce((accumulator, key) => {
    accumulator[key] = data[key];
    return accumulator;
  }, {});

  // Define the server directory.
  const serverDir = path.resolve('../server');

  // Define the full file path.
  const fullFilePath = path.join(serverDir, fileName);

  fs.writeFile(
    fullFilePath,
    `<?php\n// phpcs:ignoreFile -- !!! THIS IS AN AUTOMATICALLY GENERATED FILE - DO NOT EDIT !!!\nreturn ${jsPhpData(sortedData)};\n`,
    err => {
      if (err) {
        console.error('Error writing to file', err);
        return;
      }

      console.info(`File written successfully: ${fullFilePath}`);
    },
  );
}

/**
 * Generates metadata for all modules in PHP format.
 */
function generateAllModulesMetadataPhp() {
  const searchPattern                       = 'packages/module-library/src/components/**/*.json';
  const metadata                            = {};
  const conversionOutline                   = {};
  const moduleDefaultPrintedStyleAttributes = {};
  const moduleDefaultRenderAttributes       = {};

  glob(searchPattern, (error, files) => {
    if (error) {
      console.error(error);
    }

    files.forEach(fullFilePath => {
      // Read the file content.
      const fileContent = fs.readFileSync(fullFilePath, 'utf8');

      // Parse the JSON content.
      const parsed = JSON.parse(fileContent);

      // Get the file name.
      const fileName = path.basename(fullFilePath);

      /**
       * Extracts the module short path from the full file path to use as the metadata key.
       *
       * The module name should match the relative path from the Module Library components
       * directory. This approach maintains backward compatibility with existing module names
       * while enabling modules in subdirectories to reflect their hierarchical structure
       * in their identifier.
       *
       * This moduleShortPath serves as the source of truth for locating the module metadata
       * folder in the ModuleRegistration PHP class. The PHP class uses this path to construct
       * the full path to the module's metadata directory and load the corresponding module.json
       * file during module registration.
       *
       * @param {string} fullFilePath The complete file path to the module.json file.
       * @returns {string} The relative path from the components directory, used as the module key.
       *
       * @example
       * // Basic module in root components directory
       * // Input: 'packages/module-library/src/components/cta/module.json'
       * // Output: 'cta'
       *
       * @example
       * // Module in subdirectory (WooCommerce integration)
       * // Input: 'packages/module-library/src/components/woocommerce/product-images/module.json'
       * // Output: 'woocommerce/product-images'
       *
       * @example
       * // Deeply nested module structure
       * // Input: 'packages/module-library/src/components/foo/bar/boo/yaa/module.json'
       * // Output: 'foo/bar/boo/yaa'
       */
      const moduleShortPath = fullFilePath
        .replace('packages/module-library/src/components/', '')
        .replace(`/${fileName}`, '');

      // Remove the _comment from the parsed object.
      const parsedWithoutComment = Object.keys(parsed).reduce(
        (acc, key) => {
          if ('_comment' !== key) {
            acc[key] = parsed[key];
          }

          return acc;
        },
        {},
      );

      switch (fileName) {
        case 'module.json':
          metadata[moduleShortPath] = parsedWithoutComment;
          break;
        case 'conversion-outline.json':
          conversionOutline[moduleShortPath] = parsedWithoutComment;
          break;
        case 'module-default-printed-style-attributes.json':
          moduleDefaultPrintedStyleAttributes[moduleShortPath] = parsedWithoutComment;
          break;
        case 'module-default-render-attributes.json':
          moduleDefaultRenderAttributes[moduleShortPath] = parsedWithoutComment;
          break;
        default:
          // Do nothing.
          break;
      }
    });

    // Write metadata to a PHP file.
    writePhpFile(metadata, '_all_modules_metadata.php');

    // Write the conversion outline to a PHP file.
    writePhpFile(conversionOutline, '_all_modules_conversion_outline.php');

    // Write the module default printed style attributes to a PHP file.
    writePhpFile(moduleDefaultPrintedStyleAttributes, '_all_modules_default_printed_style_attributes.php');


    // Write the module default render attributes to a PHP file.
    writePhpFile(moduleDefaultRenderAttributes, '_all_modules_default_render_attributes.php');
  });
}

generateAllModulesMetadataPhp();
