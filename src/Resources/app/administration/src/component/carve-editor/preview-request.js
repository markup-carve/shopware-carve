export class PreviewRequest {
    constructor(fetchPreview, changed, delay = 250) {
        this.fetchPreview = fetchPreview;
        this.changed = changed;
        this.delay = delay;
        this.sequence = 0;
        this.timer = null;
        this.disposed = false;
    }

    update(payload) {
        if (this.disposed) return;
        clearTimeout(this.timer);
        const sequence = ++this.sequence;
        this.changed({ loading: true, error: null, result: null });
        this.timer = setTimeout(async () => {
            try {
                const result = await this.fetchPreview(payload);
                if (sequence === this.sequence) {
                    this.changed({ loading: false, error: null, result });
                }
            } catch (error) {
                if (sequence === this.sequence) {
                    this.changed({ loading: false, error, result: null });
                }
            }
        }, this.delay);
    }

    dispose() {
        this.disposed = true;
        ++this.sequence;
        clearTimeout(this.timer);
    }
}

export function escapeInline(value) {
    return String(value).replace(/([\\!"#$%&'()*+,\-./:;<=>?@[\]^_`{|}~])/g, '\\$1');
}

export function administrationLanguageId(shopware) {
    try {
        const context = shopware.Store?.get?.('context');
        if (context?.api?.languageId) return context.api.languageId;
    } catch (error) {
        // Shopware 6.6 has Pinia, but its context still lives in Vuex.
    }
    return shopware.State.get('context').api.languageId;
}
