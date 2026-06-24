import template from './sw-cms-el-carve.html.twig';
import { carveToHtml } from '@markup-carve/carve';

Shopware.Component.register('sw-cms-el-carve', {
    template,
    mixins: [Shopware.Mixin.getByName('cms-element')],
    created() {
        this.initElementConfig('carve');
    },
    computed: {
        html() {
            const src = this.element?.config?.content?.value ?? '';
            try {
                return carveToHtml(src, { allowRawHtml: false });
            } catch (e) {
                return '';
            }
        },
    },
});
