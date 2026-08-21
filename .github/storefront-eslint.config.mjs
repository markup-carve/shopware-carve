// Flat ESLint config for the storefront sources.
//
// It lives here, not in src/Resources/app/storefront/, for the same reason
// ci.yml gives for not adding esbuild to the admin package.json: `shopware-cli
// extension zip` runs `npm install` in any app directory that has a manifest,
// so tooling parked there ends up inside the plugin ZIP a merchant installs.
// `.github/` is already both `export-ignore`d and listed in `.sw-zip-blacklist`,
// so nothing in here can reach one.
//
// It imports no plugin and no shared preset on purpose, so `npx eslint` can run
// it with nothing installed alongside - the storefront has no package.json to
// hang devDependencies on, and should not grow one for a lane that needs no
// runtime dependency at all.
//
// Run it by hand with:
//   npx eslint --no-config-lookup --config .github/storefront-eslint.config.mjs \
//     src/Resources/app/storefront/src
export default [
    {
        files: ['**/*.js'],
        languageOptions: {
            // The storefront ships as an ES module to a browser: Shopware's
            // webpack build takes src/main.js as the plugin entry.
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                Array: 'readonly',
                Error: 'readonly',
                JSON: 'readonly',
                Promise: 'readonly',
                btoa: 'readonly',
                console: 'readonly',
                document: 'readonly',
                encodeURIComponent: 'readonly',
                fetch: 'readonly',
                unescape: 'readonly',
                window: 'readonly',
            },
        },
        // Deliberately not a style opinion - the repo has no formatter and this
        // is not the ticket that introduces one. These are the rules that catch
        // a storefront file which would actually break in a browser: a typo'd
        // global, a binding that is never what the author thought, a duplicate
        // key silently discarding a handler, code after a return.
        rules: {
            'no-undef': 'error',
            'no-unused-vars': ['error', { args: 'after-used' }],
            'no-const-assign': 'error',
            'no-dupe-keys': 'error',
            'no-dupe-args': 'error',
            'no-func-assign': 'error',
            'no-unreachable': 'error',
            'no-self-assign': 'error',
            'use-isnan': 'error',
            'valid-typeof': 'error',
        },
    },
];
