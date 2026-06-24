import template from './sw-cms-el-config-carve.html.twig';
import { carveToHtml } from '@markup-carve/carve';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-carve', {
    template,
    mixins: [Mixin.getByName('cms-element')],
    data() {
        return {
            livePreviewEnabled: true,
            allowRawHtml: false,
        };
    },
    created() {
        this.initElementConfig('carve');
        Shopware.Service('systemConfigApiService')
            .getValues('ShopwareCarve.config')
            .then((values) => {
                const lp = values['ShopwareCarve.config.livePreview'];
                this.livePreviewEnabled = lp === undefined ? true : Boolean(lp);
                const raw = values['ShopwareCarve.config.allowRawHtml'];
                this.allowRawHtml = raw === undefined ? false : Boolean(raw);
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
                return carveToHtml(this.content, { allowRawHtml: this.allowRawHtml });
            } catch (e) {
                return '';
            }
        },
    },
});
