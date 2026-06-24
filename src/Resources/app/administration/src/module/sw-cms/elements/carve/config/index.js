import template from './sw-cms-el-config-carve.html.twig';
import { carveToHtml } from '@markup-carve/carve';

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
        previewHtml() {
            try {
                return carveToHtml(this.content);
            } catch (e) {
                return '';
            }
        },
    },
});
