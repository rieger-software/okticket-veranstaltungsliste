(function (blocks, blockEditor, components, element, i18n, serverSideRender) {
    'use strict';

    var el = element.createElement;
    var blockData = window.OKTicketAppointmentsWidgetBlockData || {};
    var seriesOptions = [{ label: i18n.__('Veranstaltung wählen', 'okticket-veranstaltungsliste'), value: '' }]
        .concat(blockData.series || []);

    blocks.registerBlockType('okticket/termine', {
        apiVersion: 3,
        title: i18n.__('OKTicket Termine', 'okticket-veranstaltungsliste'),
        description: i18n.__('Zeigt die nächsten Termine einer Veranstaltungsreihe in einer Widget-Fläche.', 'okticket-veranstaltungsliste'),
        icon: 'calendar-alt',
        category: 'widgets',
        attributes: {
            eventId: { type: 'string', default: '' },
            title: { type: 'string', default: i18n.__('Nächste Aufführungen', 'okticket-veranstaltungsliste') },
            limit: { type: 'number', default: 5 },
            showAll: { type: 'boolean', default: false },
            showAvailability: { type: 'boolean', default: true }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = blockEditor.useBlockProps({ className: 'okticket-overview-block-preview' });

            return el('div', blockProps,
                el(blockEditor.InspectorControls, {},
                    el(components.PanelBody, { title: i18n.__('Termine', 'okticket-veranstaltungsliste'), initialOpen: true },
                        el(components.SelectControl, {
                            label: i18n.__('Veranstaltungsreihe', 'okticket-veranstaltungsliste'),
                            value: attributes.eventId || '',
                            options: seriesOptions,
                            onChange: function (eventId) { setAttributes({ eventId: eventId }); }
                        }),
                        el(components.TextControl, {
                            label: i18n.__('Überschrift', 'okticket-veranstaltungsliste'),
                            value: attributes.title || '',
                            onChange: function (title) { setAttributes({ title: title }); }
                        }),
                        el(components.RangeControl, {
                            label: i18n.__('Anzahl Termine', 'okticket-veranstaltungsliste'),
                            value: attributes.limit || 5,
                            min: 1,
                            max: 10,
                            disabled: !!attributes.showAll,
                            onChange: function (limit) { setAttributes({ limit: limit || 1 }); }
                        }),
                        el(components.CheckboxControl, {
                            label: i18n.__('Alle Termine anzeigen', 'okticket-veranstaltungsliste'),
                            checked: !!attributes.showAll,
                            onChange: function (showAll) { setAttributes({ showAll: showAll }); }
                        }),
                        el(components.ToggleControl, {
                            label: i18n.__('Ticket-Ampel anzeigen', 'okticket-veranstaltungsliste'),
                            checked: attributes.showAvailability !== false,
                            onChange: function (showAvailability) { setAttributes({ showAvailability: showAvailability }); }
                        })
                    )
                ),
                attributes.eventId
                    ? el(serverSideRender, { block: 'okticket/termine', attributes: attributes })
                    : el(components.Placeholder, {
                        label: i18n.__('OKTicket Termine', 'okticket-veranstaltungsliste'),
                        instructions: i18n.__('Wähle in der rechten Seitenleiste eine Veranstaltungsreihe aus.', 'okticket-veranstaltungsliste')
                    })
            );
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender));
