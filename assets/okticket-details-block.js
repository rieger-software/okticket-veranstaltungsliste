(function (blocks, blockEditor, components, element, i18n, serverSideRender) {
    'use strict';

    var el = element.createElement;
    var blockData = window.OKTicketDetailsBlockData || {};
    var seriesOptions = [{ label: i18n.__('Veranstaltung wählen', 'okticket-veranstaltungsliste'), value: '' }]
        .concat(blockData.series || []);
    var fieldOptions = blockData.fields || [];
    var vvkFieldOptions = [
        { value: 'name', label: i18n.__('Name', 'okticket-veranstaltungsliste') },
        { value: 'zusatz', label: i18n.__('Zusatz', 'okticket-veranstaltungsliste') },
        { value: 'street', label: i18n.__('Straße', 'okticket-veranstaltungsliste') },
        { value: 'zip', label: i18n.__('PLZ', 'okticket-veranstaltungsliste') },
        { value: 'city', label: i18n.__('Ort', 'okticket-veranstaltungsliste') },
        { value: 'phone', label: i18n.__('Telefon', 'okticket-veranstaltungsliste') },
        { value: 'opentimes', label: i18n.__('Öffnungszeiten', 'okticket-veranstaltungsliste') },
        { value: 'distance', label: i18n.__('Entfernung', 'okticket-veranstaltungsliste') }
    ];

    blocks.registerBlockType('okticket/veranstaltungsdetails', {
        apiVersion: 3,
        title: i18n.__('OKTicket Veranstaltungsdetails', 'okticket-veranstaltungsliste'),
        description: i18n.__('Stellt die Details einer OKTicket-Veranstaltungsreihe dynamisch dar.', 'okticket-veranstaltungsliste'),
        icon: 'tickets-alt',
        category: 'widgets',
        attributes: {
            eventId: { type: 'string', default: '' },
            fields: {
                type: 'array',
                default: fieldOptions.map(function (field) { return field.value; })
            },
            vvkEnabled: { type: 'boolean', default: false },
            vvkFields: {
                type: 'array',
                default: vvkFieldOptions.map(function (field) { return field.value; })
            },
            vvkLimit: { type: 'number', default: 5 },
            vvkSortByVenuePostcode: { type: 'boolean', default: false }
        },
        edit: function (props) {
            var blockProps = blockEditor.useBlockProps({ className: 'okticket-overview-block-preview' });
            var selectedEventId = props.attributes.eventId || '';
            var selectedFields = props.attributes.fields || [];
            var vvkEnabled = !!props.attributes.vvkEnabled;
            var selectedVvkFields = props.attributes.vvkFields || [];
            var vvkLimit = props.attributes.vvkLimit || 5;
            var vvkSortByVenuePostcode = !!props.attributes.vvkSortByVenuePostcode;
            var setAttributes = props.setAttributes;

            var toggleField = function (field, checked) {
                var fields = checked
                    ? selectedFields.concat(selectedFields.indexOf(field) === -1 ? [field] : [])
                    : selectedFields.filter(function (value) { return value !== field; });
                setAttributes({ fields: fields });
            };
            var toggleVvkField = function (field, checked) {
                var fields = checked
                    ? selectedVvkFields.concat(selectedVvkFields.indexOf(field) === -1 ? [field] : [])
                    : selectedVvkFields.filter(function (value) { return value !== field; });
                setAttributes({ vvkFields: fields });
            };

            return el('div', blockProps,
                el(blockEditor.InspectorControls, {},
                    el(components.PanelBody, {
                        title: i18n.__('Veranstaltung', 'okticket-veranstaltungsliste'),
                        initialOpen: true
                    },
                    el(components.SelectControl, {
                        label: i18n.__('Veranstaltungsreihe', 'okticket-veranstaltungsliste'),
                        value: selectedEventId,
                        options: seriesOptions,
                        onChange: function (id) { setAttributes({ eventId: id }); }
                    })),
                    el(components.PanelBody, {
                        title: i18n.__('Angezeigte Informationen', 'okticket-veranstaltungsliste'),
                        initialOpen: true
                    },
                    fieldOptions.map(function (field) {
                        return el(components.CheckboxControl, {
                            key: field.value,
                            label: field.label,
                            checked: selectedFields.indexOf(field.value) !== -1,
                            onChange: function (checked) { toggleField(field.value, checked); }
                        });
                    })),
                    el(components.PanelBody, {
                        title: i18n.__('Vorverkaufsstellen', 'okticket-veranstaltungsliste'),
                        initialOpen: false
                    },
                    el(components.CheckboxControl, {
                        label: i18n.__('Vorverkaufsstellen anzeigen', 'okticket-veranstaltungsliste'),
                        checked: vvkEnabled,
                        onChange: function (checked) { setAttributes({ vvkEnabled: checked }); }
                    }),
                    vvkEnabled && vvkFieldOptions.map(function (field) {
                        return el(components.CheckboxControl, {
                            key: field.value,
                            label: field.label,
                            checked: selectedVvkFields.indexOf(field.value) !== -1,
                            onChange: function (checked) { toggleVvkField(field.value, checked); }
                        });
                    }),
                    vvkEnabled && el(components.RangeControl, {
                        label: i18n.__('Anzahl Vorverkaufsstellen', 'okticket-veranstaltungsliste'),
                        value: vvkLimit,
                        min: 1,
                        max: 50,
                        onChange: function (value) { setAttributes({ vvkLimit: value || 1 }); }
                    }),
                    vvkEnabled && el(components.CheckboxControl, {
                        label: i18n.__('Nach PLZ des Veranstaltungsorts sortieren', 'okticket-veranstaltungsliste'),
                        help: i18n.__('Sortiert nach Luftlinien-Entfernung.', 'okticket-veranstaltungsliste'),
                        checked: vvkSortByVenuePostcode,
                        onChange: function (checked) { setAttributes({ vvkSortByVenuePostcode: checked }); }
                    }))
                ),
                selectedEventId
                    ? el(serverSideRender, {
                        block: 'okticket/veranstaltungsdetails',
                        attributes: {
                            eventId: selectedEventId,
                            fields: selectedFields,
                            vvkEnabled: vvkEnabled,
                            vvkFields: selectedVvkFields,
                            vvkLimit: vvkLimit,
                            vvkSortByVenuePostcode: vvkSortByVenuePostcode
                        }
                    })
                    : el(components.Placeholder, {
                        label: i18n.__('OKTicket Veranstaltungsdetails', 'okticket-veranstaltungsliste'),
                        instructions: i18n.__('Wähle in der rechten Seitenleiste eine Veranstaltungsreihe aus.', 'okticket-veranstaltungsliste')
                    })
            );
        },
        save: function () {
            return null;
        }
    });
}(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender));
