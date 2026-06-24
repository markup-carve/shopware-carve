import template from './sw-cms-el-carve.html.twig';
import { carveToHtml } from '@markup-carve/carve';

Shopware.Component.register('sw-cms-el-carve', {
    template,
    mixins: [Shopware.Mixin.getByName('cms-element')],
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
        html() {
            const src = this.element?.config?.content?.value ?? '';
            try {
                return carveToHtml(src, { allowRawHtml: this.allowRawHtml });
            } catch (e) {
                return '';
            }
        },
    },
});
