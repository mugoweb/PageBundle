const path = require('path');

module.exports = (Encore) => {
    Encore.addEntry('mugopage-config', [
        path.resolve(__dirname, '../public/scss/admin/config/styles.scss'),

        path.resolve(__dirname, '../public/js/admin/config/script.js'),
    ]);
    Encore.addEntry('mugopage-fieldtype-edit', [
        path.resolve(__dirname, '../public/scss/admin/fieldtype/edit.scss'),

        path.resolve(__dirname, '../public/js/admin/fieldtype/script.js'),
    ]);
    Encore.addEntry('mugopage-fieldtype-view', [
        path.resolve(__dirname, '../public/scss/admin/fieldtype/view.scss'),
    ]);
};