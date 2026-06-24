import template from './sw-cms-el-config-carve.html.twig';
import { carveToHtml } from '@markup-carve/carve';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-carve', {
    template,
    mixins: [Mixin.getByName('cms-element')],
    data() {
        return {
            livePreviewEnabled: true,
        };
    },
    created() {
        this.initElementConfig('carve');
        Shopware.Service('systemConfigApiService')
            .getValues('ShopwareCarve.config')
            .then((values) => {
                const val = values['ShopwareCarve.config.livePreview'];
                this.livePreviewEnabled = val === undefined ? true : Boolean(val);
            });
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
                return carveToHtml(this.content, { allowRawHtml: false });
            } catch (e) {
                return '';
            }
        },
    },
});
