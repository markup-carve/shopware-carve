import template from './sw-cms-el-config-carve.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-carve', {
    template,
    mixins: [Mixin.getByName('cms-element')],
    created() {
        this.initElementConfig('carve');
    },
    computed: {
        content: {
            get() {
                return this.element?.config?.content?.value ?? '';
            },
            set(value) {
                this.element.config.content.value = value;
                this.$emit('element-update', this.element);
            },
        },
    },
});
