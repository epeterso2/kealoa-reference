/**
 * KEALOA Reference - Gutenberg Blocks Editor Script
 *
 * Registers all KEALOA blocks for the WordPress block editor.
 *
 * @package KEALOA_Reference
 */

(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, SelectControl, RangeControl, Placeholder } = wp.components;
    const { __ } = wp.i18n;
    const { serverSideRender: ServerSideRender } = wp;

    // Get data passed from PHP
    const kealoaData = window.kealoaBlocksData || { rounds: [], persons: [] };

    /**
     * KEALOA Rounds Table Block
     */
    registerBlockType('kealoa/rounds-table', {
        title: __('KEALOA Rounds Table', 'kealoa-reference'),
        description: __('Displays a table of all KEALOA rounds with dates, episodes, solutions, and results.', 'kealoa-reference'),
        icon: 'editor-table',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('rounds', 'kealoa-reference'), __('table', 'kealoa-reference')],
        attributes: {
            limit: {
                type: 'number',
                default: 50
            },
            order: {
                type: 'string',
                default: 'DESC'
            }
        },

        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { limit, order } = attributes;

            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('Table Settings', 'kealoa-reference'), initialOpen: true },
                        createElement(RangeControl, {
                            label: __('Number of Rounds', 'kealoa-reference'),
                            value: limit,
                            onChange: function (value) { setAttributes({ limit: value }); },
                            min: 5,
                            max: 200,
                            step: 5
                        }),
                        createElement(SelectControl, {
                            label: __('Order', 'kealoa-reference'),
                            value: order,
                            options: [
                                { label: __('Newest First', 'kealoa-reference'), value: 'DESC' },
                                { label: __('Oldest First', 'kealoa-reference'), value: 'ASC' }
                            ],
                            onChange: function (value) { setAttributes({ order: value }); }
                        })
                    )
                ),
                createElement(ServerSideRender, {
                    block: 'kealoa/rounds-table',
                    attributes: attributes
                })
            );
        },

        save: function () {
            // Server-side rendering
            return null;
        }
    });

    /**
     * KEALOA Round View Block
     */
    registerBlockType('kealoa/round-view', {
        title: __('KEALOA Round View', 'kealoa-reference'),
        description: __('Displays a single KEALOA round with all clues, guesses, and results.', 'kealoa-reference'),
        icon: 'media-document',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('round', 'kealoa-reference'), __('clues', 'kealoa-reference')],
        attributes: {
            roundId: {
                type: 'number',
                default: 0
            }
        },

        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { roundId } = attributes;

            // Build options for round selection
            const roundOptions = [
                { label: __('— Select a Round —', 'kealoa-reference'), value: 0 }
            ];
            
            kealoaData.rounds.forEach(function (round) {
                roundOptions.push({
                    label: round.date + ' (Episode ' + round.episode + ')',
                    value: round.id
                });
            });

            if (!roundId) {
                return createElement(
                    Fragment,
                    null,
                    createElement(
                        InspectorControls,
                        null,
                        createElement(
                            PanelBody,
                            { title: __('Round Selection', 'kealoa-reference'), initialOpen: true },
                            createElement(SelectControl, {
                                label: __('Select Round', 'kealoa-reference'),
                                value: roundId,
                                options: roundOptions,
                                onChange: function (value) { setAttributes({ roundId: parseInt(value, 10) }); }
                            })
                        )
                    ),
                    createElement(
                        Placeholder,
                        {
                            icon: 'media-document',
                            label: __('KEALOA Round View', 'kealoa-reference'),
                            instructions: __('Select a round from the block settings in the sidebar.', 'kealoa-reference')
                        },
                        createElement(SelectControl, {
                            value: roundId,
                            options: roundOptions,
                            onChange: function (value) { setAttributes({ roundId: parseInt(value, 10) }); }
                        })
                    )
                );
            }

            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('Round Selection', 'kealoa-reference'), initialOpen: true },
                        createElement(SelectControl, {
                            label: __('Select Round', 'kealoa-reference'),
                            value: roundId,
                            options: roundOptions,
                            onChange: function (value) { setAttributes({ roundId: parseInt(value, 10) }); }
                        })
                    )
                ),
                createElement(ServerSideRender, {
                    block: 'kealoa/round-view',
                    attributes: attributes
                })
            );
        },

        save: function () {
            return null;
        }
    });

    /**
     * KEALOA Person View Block
     */
    registerBlockType('kealoa/person-view', {
        title: __('KEALOA Person View', 'kealoa-reference'),
        description: __('Displays a person\'s KEALOA statistics, round history, and performance metrics.', 'kealoa-reference'),
        icon: 'admin-users',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('person', 'kealoa-reference'), __('player', 'kealoa-reference')],
        attributes: {
            personId: {
                type: 'number',
                default: 0
            }
        },

        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { personId } = attributes;

            // Build options for person selection
            const personOptions = [
                { label: __('— Select a Person —', 'kealoa-reference'), value: 0 }
            ];
            
            kealoaData.persons.forEach(function (person) {
                personOptions.push({
                    label: person.name,
                    value: person.id
                });
            });

            if (!personId) {
                return createElement(
                    Fragment,
                    null,
                    createElement(
                        InspectorControls,
                        null,
                        createElement(
                            PanelBody,
                            { title: __('Person Selection', 'kealoa-reference'), initialOpen: true },
                            createElement(SelectControl, {
                                label: __('Select Person', 'kealoa-reference'),
                                value: personId,
                                options: personOptions,
                                onChange: function (value) { setAttributes({ personId: parseInt(value, 10) }); }
                            })
                        )
                    ),
                    createElement(
                        Placeholder,
                        {
                            icon: 'admin-users',
                            label: __('KEALOA Person View', 'kealoa-reference'),
                            instructions: __('Select a person from the block settings in the sidebar.', 'kealoa-reference')
                        },
                        createElement(SelectControl, {
                            value: personId,
                            options: personOptions,
                            onChange: function (value) { setAttributes({ personId: parseInt(value, 10) }); }
                        })
                    )
                );
            }

            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('Person Selection', 'kealoa-reference'), initialOpen: true },
                        createElement(SelectControl, {
                            label: __('Select Person', 'kealoa-reference'),
                            value: personId,
                            options: personOptions,
                            onChange: function (value) { setAttributes({ personId: parseInt(value, 10) }); }
                        })
                    )
                ),
                createElement(ServerSideRender, {
                    block: 'kealoa/person-view',
                    attributes: attributes
                })
            );
        },

        save: function () {
            return null;
        }
    });

})(window.wp);
