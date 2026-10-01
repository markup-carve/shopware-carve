import inheritTemplate from './carve-inherit-wrapper.html.twig';
import template from './sw-cms-el-config-carve.html.twig';

Shopware.Component.register('carve-inherit-wrapper', {
    template: inheritTemplate,
    props: { field: String, element: Object, label: String },
    computed: {
        hasNativeWrapper() { return Shopware.Component.getComponentRegistry().has('sw-cms-inherit-wrapper'); },
    },
});

Shopware.Component.register('sw-cms-el-config-carve', {
    template,
    mixins: [Shopware.Mixin.getByName('cms-element')],
    created() { this.initElementConfig('carve'); },
    methods: {
        updateContent(value) {
            this.element.config.content.value = value;
            this.$emit('element-update', this.element);
        },
    },
});
