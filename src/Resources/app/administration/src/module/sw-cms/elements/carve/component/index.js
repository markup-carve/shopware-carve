import template from './sw-cms-el-carve.html.twig';

Shopware.Component.register('sw-cms-el-carve', {
    template,
    mixins: [Shopware.Mixin.getByName('cms-element')],
    created() {
        this.initElementConfig('carve');
    },
    computed: {
        source() {
            return this.element?.config?.content?.value ?? '';
        },
    },
});
