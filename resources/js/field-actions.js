import BillboardFieldtype from './components/BillboardFieldtype.vue';

function placementHandle(confirmation) {
    const value = confirmation?.processed?.placement ?? confirmation?.values?.placement;

    if (Array.isArray(value)) {
        return value.find((item) => item) ?? null;
    }

    return value || null;
}

function insertIntoMarkdown({ value, update, confirmation }) {
    const handle = placementHandle(confirmation);

    if (! handle) {
        return;
    }

    const snippet = `{{ billboard:${handle} }}`;
    const current = typeof value === 'string' ? value : '';

    update(current.trim() === '' ? `${snippet}\n` : `${current.replace(/\s*$/, '')}\n\n${snippet}\n`);
}

function insertIntoBard({ value, update, confirmation }) {
    const handle = placementHandle(confirmation);

    if (! handle) {
        return;
    }

    if (! Array.isArray(value)) {
        insertIntoMarkdown({ value, update, confirmation });

        return;
    }

    update([
        ...value,
        {
            type: 'set',
            attrs: {
                id: Math.random().toString(36).slice(2, 10),
                enabled: true,
                values: {
                    type: 'advertisement',
                    placement: handle,
                },
            },
        },
    ]);
}

function insertIntoReplicator({ value, update, confirmation }) {
    const handle = placementHandle(confirmation);

    if (! handle) {
        return;
    }

    const rows = Array.isArray(value) ? value : [];

    update([
        ...rows,
        {
            type: 'advertisement',
            enabled: true,
            placement: handle,
            _id: Math.random().toString(36).slice(2, 10),
        },
    ]);
}

const confirm = {
    title: 'Insert advertisement',
    buttonText: 'Insert',
    fields: {
        placement: {
            type: 'billboard',
            display: 'Placement',
            validate: ['required'],
            max_items: 1,
            dictionary: { type: 'ad_placements' },
        },
    },
};

Statamic.booting(() => {
    Statamic.$components.register('billboard-fieldtype', BillboardFieldtype);

    Statamic.$fieldActions.add('markdown-fieldtype', {
        title: 'Insert advertisement',
        icon: 'megaphone',
        confirm,
        run: insertIntoMarkdown,
    });

    Statamic.$fieldActions.add('bard-fieldtype', {
        title: 'Insert advertisement',
        icon: 'megaphone',
        confirm,
        visible: ({ config }) => Boolean(config?.sets?.advertisement),
        run: insertIntoBard,
    });

    Statamic.$fieldActions.add('replicator-fieldtype', {
        title: 'Insert advertisement',
        icon: 'megaphone',
        confirm,
        visible: ({ config }) => Boolean(config?.sets?.advertisement),
        run: insertIntoReplicator,
    });
});
