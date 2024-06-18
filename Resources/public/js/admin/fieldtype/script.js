import Sortable from "../lib/sortablejs/modular/sortable.complete.esm";

/**
 * String convention
 * These variables should match the constants in MugoPageService
 */
const ITEM_TYPE_ZONE = 'zone'
const ITEM_TYPE_LAYOUT = 'layout'
const ITEM_TYPE_BLOCK = 'block'

const ITEM_ACTION_NEW = 'new'
const ITEM_ACTION_EDIT = 'edit'

/**
 * Defines the scope of the edit field
 * Do not use id attribute to identify objects in the edit view. Content types
 * can have more than one mugopage field and that will break the UI.
 */
const mugoPageFields = document.querySelectorAll('section.mugopage-edit-field-section');

// these variables are required to load UDW on the page
const udwContainer = document.getElementById('react-udw');
let udwRoot = null;

Array.prototype.forEach.call(mugoPageFields, function(mugoPageField) {

    /**
     * Set general elements
     */
    const layoutSection = mugoPageField.querySelector('.mugopage-layout-section');
    const layoutBtnContainer = layoutSection.querySelector('.accordion-layout .accordion-body .layout-options-section');
    const ibexaTextAreaField = mugoPageField.querySelector('.mugopage-ibexa-field > textarea');

    const zoneSection = mugoPageField.querySelector('.mugopage-zone-section');

    const blockRepositorySection = mugoPageField.querySelector('.mugopage-block-repository');

    /**
     * Sets the MugoPage configuration in the ibexa text area field
     */
    const storeBlockConfiguration = () => {

        // defined block configuration object
        let blockConfiguration = {}

        // get layout information
        let layoutIdentifier = layoutSection.getAttribute('data-identifier');
        blockConfiguration.layout = layoutIdentifier

        // get zone/block information
        let zonesArray = [];
        let zoneActiveBtns = zoneSection.querySelectorAll('.mugopage-tabs button.mugopage-tabitem:not(.d-none)');
        Array.prototype.forEach.call(zoneActiveBtns, function(zoneActiveBtn) {

            // get zones
            let zoneIdentifier = zoneActiveBtn.getAttribute('data-identifier');
            let zoneObj = {}
            zoneObj.identifier = zoneIdentifier;

            // get blocks
            let blocks = zoneSection.querySelectorAll('.mugopage-tabs-container .accordion-collapse[data-identifier="'+zoneIdentifier+'"] .blocks-section .block-item');
            let blocksArray = [];
            Array.prototype.forEach.call(blocks, function(block) {

                let blockObj = {}

                // get block type information
                let type = {};
                type.identifier = block.getAttribute('data-identifier');
                type.name = block.getAttribute('data-name');
                blockObj.type = type;

                // get block fields
                let content = {}
                content.name = block.querySelector('.accordion-body .content-fields input[name="name"]').value;
                content.id = block.querySelector('.accordion-body .content-fields div.block-id').innerHTML;
				blockObj.content = content;

                // get custom attributes
                let customAttributeArray = [];
                let customAttributeFieldSets = block.querySelectorAll('.custom-attributes [data-type]');
                Array.prototype.forEach.call(customAttributeFieldSets, function(customAttributeFieldSet) {

					let caIdentifier = '';
					let caType = '';
					let caValue = '';

					switch (customAttributeFieldSet.getAttribute('data-type')){

						case 'string':
						case 'integer':
                                                case 'text':
							caIdentifier = customAttributeFieldSet.getAttribute('name');
							caType = customAttributeFieldSet.getAttribute('data-type');
							caValue = customAttributeFieldSet.value;
							break;
                                                case 'choice':
							caIdentifier = customAttributeFieldSet.getAttribute('data-name');
							caType = customAttributeFieldSet.getAttribute('data-type');
                                                        switch(customAttributeFieldSet.getAttribute('data-choicetype'))
                                                        {
                                                            case 'radio':
                                                                let selectedRadio = customAttributeFieldSet.querySelector('input[type=radio]:checked');
                                                                if(selectedRadio)
                                                                {
                                                                    customAttributeFieldSet.setAttribute('data-value', selectedRadio.value);
                                                                }
                                                                else
                                                                {
                                                                    customAttributeFieldSet.setAttribute('data-value', 'none');
                                                                }
                                                                break;
                                                            case 'select':
                                                                let selectedOption = customAttributeFieldSet.querySelector('select option:checked');
                                                                if(selectedOption)
                                                                {
                                                                    customAttributeFieldSet.setAttribute('data-value', selectedOption.value);
                                                                }
                                                                else
                                                                {
                                                                    customAttributeFieldSet.setAttribute('data-value', 'none');
                                                                }
                                                                break;
                                                            case 'select_multiple':
                                                                let selectedOptions = customAttributeFieldSet.querySelectorAll('select option:checked');
                                                                if(selectedOptions.length)
                                                                {
                                                                    customAttributeFieldSet.setAttribute('data-value', [...selectedOptions].map(item => item.value).join(','));
                                                                }
                                                                else
                                                                {
                                                                    customAttributeFieldSet.setAttribute('data-value', 'none');
                                                                }
                                                                break;
                                                            case 'checkbox':
                                                                let checkedOptions = customAttributeFieldSet.querySelectorAll('input[type=checkbox]:checked');
                                                                if(checkedOptions.length)
                                                                {
                                                                    customAttributeFieldSet.setAttribute('data-value', [...checkedOptions].map(item => item.value).join(','));
                                                                }
                                                                else
                                                                {
                                                                    customAttributeFieldSet.setAttribute('data-value', 'none');
                                                                }
                                                                break;
                                                        }
							caValue = customAttributeFieldSet.getAttribute('data-value');
							break;

						case 'contentrelation':
							caIdentifier = customAttributeFieldSet.getAttribute('data-identifier');
							caType = customAttributeFieldSet.getAttribute('data-type');
							caValue = [];

							let relatedContentItems = customAttributeFieldSet.querySelectorAll('div.content-related-item');
							Array.prototype.forEach.call(relatedContentItems, function(relatedContentItem) {
								caValue.push(relatedContentItem.getAttribute('data-relatedcontentid'));
							})
							break;

					}

					if (caValue) {
						let caObj = {};
						caObj.identifier = caIdentifier;
						caObj.type = caType;
						caObj.value = caValue;
						customAttributeArray.push(caObj);
					}

                })

                blockObj.custom_attributes = customAttributeArray;

                blocksArray.push(blockObj);

            })

            zoneObj.blocks = blocksArray;

            zonesArray.push(zoneObj)

        })

        if (zonesArray.length > 0) {
            blockConfiguration.zones = zonesArray;
        }

        // add the configuration to ibexa text area
        ibexaTextAreaField.value = JSON.stringify(blockConfiguration);

    }

    /**
     * Sets the active layout based on identifier
     * @param String identifier
     * @param Boolean showNotification
     */
    const setActiveLayout = (identifier, showNotification) => {

        // search the layout button based on the identifier
        let activeLayoutBtn = layoutBtnContainer.querySelector('button[data-identifier="'+identifier+'"]')
        if (!activeLayoutBtn){
            ibexa.helpers.notification.showErrorNotification('Layout identifier '+identifier+' was not found in the available options list!');
            return false;
        }
        let name = activeLayoutBtn.getAttribute('data-name');
        let zones = activeLayoutBtn.getAttribute('data-zones').split('|#|');

        // remove active class from buttons
        let noLongerActiveBtns = layoutBtnContainer.querySelectorAll('button.active');
        Array.prototype.forEach.call(noLongerActiveBtns, function(noLongerActiveBtn) {
            noLongerActiveBtn.classList.remove('active')
        })

        // add active lass to active button
        activeLayoutBtn.classList.add('active');

        // add data to layout section
        layoutSection.setAttribute('data-identifier', identifier);
        layoutSection.setAttribute('data-name', name);
        layoutSection.querySelector('.accordion-header > .title').innerHTML = name;

        // collapse layout accordion
        let layoutAccordionBtn = layoutSection.querySelector('.accordion-header button.btn-choose-layout');
        if (layoutAccordionBtn && layoutAccordionBtn.getAttribute('aria-expanded') == 'true') {
            let layoutAccordion = layoutSection.querySelector('.mugopage-select-layout-accordion');
            let bsLayoutAccordion = bootstrap.Collapse.getOrCreateInstance(layoutAccordion);
            bsLayoutAccordion.hide();
        }

        // show notification that layout is set
        if (showNotification) {
            ibexa.helpers.notification.showSuccessNotification('Tha layout ' + name + ' is ready to use!');
        }

        // load the related zones
        loadZones(zones, showNotification);

        // stores the configuration on ibexa field
        storeBlockConfiguration();
    }

    /**
     * Set Layout Confirmation Modal actions
     */
    const modalConfirmLayoutChange = mugoPageField.querySelector('.modal-confirm-layout-change');
    if (modalConfirmLayoutChange){

        let btnConfirmLayoutChange = modalConfirmLayoutChange.querySelector('.btn-confirm-layout-change');

        // show modal event
        modalConfirmLayoutChange.addEventListener('show.bs.modal', event => {

            // get the choosen layout information
            let layoutbtn = event.relatedTarget;
            let layoutname = layoutbtn.getAttribute('data-name');
            let layoutidentifier = layoutbtn.getAttribute('data-identifier');

            // update the modal content
            modalConfirmLayoutChange.querySelector('.modal-body .name').innerHTML = layoutname;
            modalConfirmLayoutChange.querySelector('.modal-body .identifier').innerHTML = layoutidentifier;

            // add attributes to modal confirmation button
            btnConfirmLayoutChange.setAttribute('data-identifier', layoutidentifier);

        })

        // Confirm Layout click event
        btnConfirmLayoutChange.addEventListener('click', () => {

            // close modal
            let bsModalConfirmLayoutChange = bootstrap.Modal.getOrCreateInstance(modalConfirmLayoutChange);
            bsModalConfirmLayoutChange.hide();

            // set active layout
            let layoutidentifier = btnConfirmLayoutChange.getAttribute('data-identifier');
            setActiveLayout(layoutidentifier, true);

        })

    }

    /**
     * Load zones of active layout
     * @param Array zonesArray
     * @param Boolean showNotification
     */
    const loadZones = (zonesArray, showNotifications) => {

        // hide all zone buttons on the page
        const zoneTabBtns = zoneSection.querySelectorAll('.mugopage-tabs button.mugopage-tabitem');
        Array.prototype.forEach.call(zoneTabBtns, function(zoneTabBtn) {
            zoneTabBtn.classList.add('d-none');

            // handles the collapse visibility manually because Collapse does not work correctly with hidden elements
            zoneTabBtn.classList.add('collapsed');
            zoneTabBtn.setAttribute('aria-expanded', "false");
        })

        // show only zones available for the layout
        Array.prototype.forEach.call(zonesArray, function(zoneIdentifier) {
            let availableZoneButton = zoneSection.querySelector('.mugopage-tabs button.mugopage-tabitem[data-identifier="'+zoneIdentifier+'"]');
            if (availableZoneButton){
                availableZoneButton.classList.remove('d-none');
            } else {
                if (showNotifications) {
                    ibexa.helpers.notification.showWarningNotification('The zone identifier ' + zoneIdentifier + ' was not found in the block of configuration! Refresh the page to load the correct MugoPage configuration.');
                }
            }
        })

        // handles the collapse visibility manually because Collapse does not work correctly with hidden elements
        const zoneBlocks = zoneSection.querySelectorAll('.mugopage-tabs-container .accordion-collapse');
        Array.prototype.forEach.call(zoneBlocks, function(zoneBlock) {
            zoneBlock.classList.remove('show');
        })

        // remove any existing block from the zones
        Array.prototype.forEach.call(zoneBlocks, function(zoneBlock) {
            let blocksToRemove = zoneBlock.querySelectorAll('.mugopage-tabs-container .blocks-section .block-item');
            Array.prototype.forEach.call(blocksToRemove, function(blockToRemove) {
                blockToRemove.parentNode.removeChild(blockToRemove);
            })
        })

        // show zone section
        zoneSection.classList.remove('d-none');

    }

    /**
     * Remove blocks from zones
     */
    const removeBlocksFromZones = (event) => {

        // get the button
        let removeBtn = event.currentTarget;

        // get block item
        let blockItem = removeBtn.closest('.block-item');
        if (blockItem) {

            let blockName = blockItem.getAttribute('data-name');
            let blockIdentifier = blockItem.getAttribute('data-identifier');
            ibexa.helpers.notification.showSuccessNotification('The block identifier '+blockIdentifier+' was removed!');

            blockItem.parentNode.removeChild(blockItem);
        }

        storeBlockConfiguration();

    }

    /**
     * Removes the related content object from the list
     * @param event
     */
    const removeRelatedContent = (event) => {

        // get the related item to remove
        const btnRemove = event.currentTarget;
        let contentRelatedItem = btnRemove.closest('.content-related-item');

        if (contentRelatedItem){

            // get the related list
            let contentRelatedList = contentRelatedItem.parentNode;

            // remove the related content item
            contentRelatedItem.parentNode.removeChild(contentRelatedItem);

            // test the number of items
            let remainingItems = contentRelatedList.querySelectorAll('.content-related-item');
            if (remainingItems.length == 0) {
                contentRelatedList.querySelector('span.empty').classList.remove('d-none');
            } else {
                contentRelatedList.querySelector('span.empty').classList.add('d-none');
            }

            storeBlockConfiguration();

        }

    }

    const addRelatedContent = (relatedContentSectionEl, data) => {

        // get related content data
        let itemName = data.name;
        let itemIdentifier = data.identifier;
        let itemContentId = data.contentid;
        let itemLocationId = data.locationid;

        // get elements from block
        let relatedItemsBlock = relatedContentSectionEl.querySelector('.related-items-list');
        let relatedItemRepo = relatedContentSectionEl.querySelector('.content-related-repo .content-related-item');

        // create a new clone related block item
        let newContentRelationItem = relatedItemRepo.cloneNode(true);

        // add content to the new related content
        newContentRelationItem.querySelector('.content .content-name').innerHTML = itemName;
        newContentRelationItem.querySelector('.content .content-identifier').innerHTML = itemIdentifier;
        newContentRelationItem.querySelector('.content .content-content-id').innerHTML = itemContentId;
        newContentRelationItem.querySelector('.content .content-location-id').innerHTML = itemLocationId;

        newContentRelationItem.setAttribute('data-relatedcontentid', itemContentId);

        // add events
        newContentRelationItem.querySelector('.btn-accordion.remove').addEventListener('click', removeRelatedContent);

        // inject the new block on the list
        relatedItemsBlock.append(newContentRelationItem);

        // hide empty message
        relatedItemsBlock.querySelector('span.empty').classList.add('d-none');

    }

    /**
     * UDW functions
     */
    const closeUDW = () => udwRoot.unmount();
    const onCancel = () => closeUDW();

    const onConfirm = (btn, data) => {

        const relatedContentSection = btn.closest('.related-content-section');
        if (relatedContentSection){

            Array.prototype.forEach.call(data, function(item) {

                // prepare data
                let relatedData = {};

                // get related content data
                relatedData.name = item.ContentInfo.Content.Name;
                relatedData.identifier = item.ContentInfo.Content.ContentTypeInfo.identifier;
                relatedData.contentid = item.ContentInfo.Content._id;
                relatedData.locationid = item.id;

                addRelatedContent(relatedContentSection, relatedData);

            })

            storeBlockConfiguration();

        }

        closeUDW();

    };

    const renderUDW = (event) => {

        event.preventDefault();

		let btnTrigger = event.currentTarget;

        const config = JSON.parse(btnTrigger.getAttribute('data-udw-config'));

        // override config with data from btnTrigger
        config.multiple = true;
        config.title = btnTrigger.getAttribute('data-udw-title');
		config.multiple_items_limit = btnTrigger.getAttribute('data-udw-maximumitems');
		if (btnTrigger.getAttribute('data-udw-allowedtypes')) {
			config.allowedContentTypes = btnTrigger.getAttribute('data-udw-allowedtypes');
		}

        udwRoot = ReactDOM.createRoot(udwContainer);
        udwRoot.render(
            React.createElement(ibexa.modules.UniversalDiscovery, {
                onConfirm: onConfirm.bind(null, event.currentTarget),
                onCancel,
                ...config,
            }),
        );

    }

    /**
     * Sets the Block name and identifier based on the values in the block
     * @param blockItem
     */
    const setBlockName = (blockItem) => {

        let blockName = blockItem.querySelector('.accordion-body input[name="name"]').value;
        let blockIdentifier = blockItem.querySelector('.accordion-body .mugopage-input.block-id').innerHTML;

        blockItem.querySelector('.accordion-header div.block-name').innerHTML = blockName;
        blockItem.querySelector('.accordion-header div.block-identifier').innerHTML = blockIdentifier;

    }

    /**
     * Create unique block ids
     * The block ids are based on time stamp and two random numbers
     */
    const createUniqueBlockId = () => {

        let now = new Date();
        const timestamp = now.getTime();
        const preRandom = Math.floor(Math.random() * 999999);
        const postRandom = Math.floor(Math.random() * 999999);

        return 'block_'+preRandom+'_'+timestamp+'_'+postRandom;
    }

    /**
     * Add blocks to zones
     * @param String zoneIdentifier
     * @param String blockIdentifier
     * @param Boolean showNotification
     * @param Array data
     */
    const addBlocksToZones = (zoneIdentifier, blockIdentifier, showNotifications, data) => {

        // fetch the block from repository
        const blockTemplate = blockRepositorySection.querySelector('div.block-item[data-identifier="'+blockIdentifier+'"]');
        if (blockTemplate) {

            // confirm zone relation
            let relatedZones = blockTemplate.getAttribute('data-zones').split('|#|');
            if (relatedZones.includes(zoneIdentifier)){

                // clone the block
                let newBlock = blockTemplate.cloneNode(true);

                // create unique block id
                let blockId = createUniqueBlockId();

                // load data if available | it will also override the unique block id
                if (data && typeof data.content != "undefined"){

                    // load content
                    newBlock.querySelector('.accordion-body .content-fields input[name="name"]').value = data.content.name;
                    newBlock.querySelector('.accordion-header .block-name').innerHTML = data.content.name;

                    // update block id
                    blockId = data.content.id;

                    // load related content
                    if (typeof data.content.related_content != "undefined"){

                        let relatedContentSection = newBlock.querySelector('.related-content-section');

                        Array.prototype.forEach.call(data.content.related_content, function(relatedContent) {

                            let relatedItem = mugoPageField.querySelector('.related-content-to-load div[data-contentid="'+relatedContent+'"]');
                            if (relatedItem) {

                                let relatedData = {};
                                relatedData.name = relatedItem.getAttribute('data-name');
                                relatedData.identifier = relatedItem.getAttribute('data-identifier');
                                relatedData.contentid = relatedItem.getAttribute('data-contentid');
                                relatedData.locationid = relatedItem.getAttribute('data-locationid');

                                addRelatedContent(relatedContentSection, relatedData);

                            }

                        })

                    }

                    // load custom attributes
                    if (typeof data.custom_attributes != "undefined"){
                        Array.prototype.forEach.call(data.custom_attributes, function(customAttribute) {

                            switch (customAttribute.type) {
                                case 'string':
                                case 'integer':
                                    let inputCustomField = newBlock.querySelector('.accordion-body .custom-attributes input[name="'+customAttribute.identifier+'"][data-type="' + customAttribute.type + '"]');
                                    if (inputCustomField){
                                        inputCustomField.value = customAttribute.value;
                                    }
                                    break;
                                case 'text':
                                    let textareaCustomField = newBlock.querySelector('.accordion-body .custom-attributes textarea[name="'+customAttribute.identifier+'"][data-type="' + customAttribute.type + '"]');
                                    if (textareaCustomField){
                                        textareaCustomField.value = customAttribute.value;
                                    }
                                    break;
                                case 'choice':
                                    let choiceCustomField = newBlock.querySelector('.accordion-body .custom-attributes div[data-name="'+customAttribute.identifier+'"][data-type="' + customAttribute.type + '"]');
                                    if (choiceCustomField){
                                        choiceCustomField.setAttribute('data-value', customAttribute.value);
                                        switch(choiceCustomField.getAttribute('data-choicetype'))
                                        {
                                            case 'radio':
                                                let checkedRadio = choiceCustomField.querySelector('input[type="radio"][value="' + customAttribute.value + '"]');
                                                if(checkedRadio)
                                                {
                                                    checkedRadio.checked = true;
                                                }
                                                break;
                                            case 'select':
                                                let selectedOption = choiceCustomField.querySelector('select option[value="' + customAttribute.value + '"]');
                                                if(selectedOption)
                                                {
                                                    selectedOption.selected = true;
                                                }
                                                break;
                                            case 'select_multiple':
                                                let selectedValues = customAttribute.value.split(',').map(item => item.trim());
                                                for(var x in selectedValues)
                                                {
                                                    let selectedOption = choiceCustomField.querySelector('select option[value="' + selectedValues[x] + '"]');
                                                    if(selectedOption)
                                                    {
                                                        selectedOption.selected = true;
                                                    }
                                                }
                                                break;
                                            case 'checkbox':
                                                let checkedValues = customAttribute.value.split(',').map(item => item.trim());
                                                for(var x in checkedValues)
                                                {
                                                    let selectedOption = choiceCustomField.querySelector('input[type="checkbox"][value="' + checkedValues[x] + '"]');
                                                    if(selectedOption)
                                                    {
                                                        selectedOption.checked = true;
                                                    }
                                                }
                                                break;
                                        }
                                    }
                                    break;
                                case 'contentrelation':
                                    let relatedContentSection = newBlock.querySelector('.related-items-list[data-identifier="'+customAttribute.identifier+'"][data-type="' + customAttribute.type + '"]').closest('.related-content-section');

                                    Array.prototype.forEach.call(customAttribute.value, function(relatedContent) {

                                            let relatedItem = mugoPageField.querySelector('.related-content-to-load div[data-contentid="'+relatedContent+'"]');
                                            if (relatedItem) {

                                                    let relatedData = {};
                                                    relatedData.name = relatedItem.getAttribute('data-name');
                                                    relatedData.identifier = relatedItem.getAttribute('data-identifier');
                                                    relatedData.contentid = relatedItem.getAttribute('data-contentid');
                                                    relatedData.locationid = relatedItem.getAttribute('data-locationid');

                                                    addRelatedContent(relatedContentSection, relatedData);

                                            }

                                    })
                                    break;
                                default:
                                    ibexa.helpers.notification.showWarningNotification('Loader ' + customAttribute.type + ' was not found on the page!');
                                    break;
                            }

                        })
                    }

                }

                // set the block id
                newBlock.querySelector('.accordion-body .content-fields div.block-id').innerHTML = blockId;
                newBlock.querySelector('.accordion-header .block-identifier').innerHTML = blockId;

                // prepare unique attribute/block id
                const accordionId = 'accordion-randomid-'+blockId;

                // update ids and data in the new block
                newBlock.querySelector('.accordion-header button.accordion-action').setAttribute('data-bs-target', '#'+accordionId);
                newBlock.querySelector('.accordion-header button.accordion-action').setAttribute('aria-controls', accordionId);
                newBlock.querySelector('div.accordion-collapse').setAttribute('id', accordionId);

                // add new class
                newBlock.classList.add('new-block');

                // add events
                let removeBlockBtn = newBlock.querySelector('.block-item button.btn-accordion.remove');
                removeBlockBtn.addEventListener('click', removeBlocksFromZones);

                // update block name on section header
                let blockNameField = newBlock.querySelector('.accordion-body input[name="name"]');
                blockNameField.addEventListener('keyup', (event) => {
                    setBlockName(newBlock);
                });
                blockNameField.addEventListener('change', (event) => {
                    setBlockName(newBlock);
                });

                // store data in input change
                let inputFields = newBlock.querySelectorAll('.accordion-body input,.accordion-body select,.accordion-body textarea');
                Array.prototype.forEach.call(inputFields, function(inputField) {
                    inputField.addEventListener('change', storeBlockConfiguration);
                })

                // add UDW to content relation fields
                let btnsAddRelatedContentToBlock = newBlock.querySelectorAll('button.btn-add-related-content-to-block');
                if (btnsAddRelatedContentToBlock){

					Array.prototype.forEach.call(btnsAddRelatedContentToBlock, function(btnAddRelatedContentToBlock) {

						btnAddRelatedContentToBlock.addEventListener('click', renderUDW);

						let contentRelationList = btnAddRelatedContentToBlock.closest('.related-content-section').querySelector('.related-content-section .related-items-list');
						new Sortable(contentRelationList, {
							animation: 500,
							ghostClass: 'accordion-item-ghost',
							handle: '.button-handler-relation', // handle's class
							onEnd: function (evt, originalEvent) {
								storeBlockConfiguration()
							},
						});

					});

                }

                // add the new block to the zone
                const blockListInZoneSection = zoneSection.querySelector('.mugopage-tabs-container .accordion-collapse[data-identifier="'+zoneIdentifier+'"] .blocks-section');
                if (blockListInZoneSection) {

                    blockListInZoneSection.append(newBlock);
                    if (showNotifications) {
                        ibexa.helpers.notification.showSuccessNotification('The block identifier ' + blockIdentifier + ' was created in the zone identifier ' + zoneIdentifier + '!');
                    }

                } else {
                    if (showNotifications) {
                        ibexa.helpers.notification.showWarningNotification('The zone identifier ' + zoneIdentifier + ' was not found on the page! Refresh the page to load the correct MugoPage configuration.');
                    }
                }

            } else {
                if (showNotifications) {
                    ibexa.helpers.notification.showWarningNotification('The block identifier ' + blockIdentifier + ' is not configured for the zone identifier ' + zoneIdentifier + '! Refresh the page to load the correct MugoPage configuration.');
                }
            }

        } else {
            if (showNotifications) {
                ibexa.helpers.notification.showWarningNotification('The block identifier ' + blockIdentifier + ' was not found in the block of configuration! Refresh the page to load the correct MugoPage configuration.');
            }
        }

    }

    /**
     * Prepare the add new block to section modal
     */
    const modalAddBlockToZone = zoneSection.querySelector('div.modal-add-block-to-zone');
    if (modalAddBlockToZone){
        modalAddBlockToZone.addEventListener('show.bs.modal', event => {

            // get the zone button
            let addBlockBtnFromZone = event.relatedTarget;

            // get zone data
            let zoneIdentifier = addBlockBtnFromZone.getAttribute('data-identifier');
            let zoneName = addBlockBtnFromZone.getAttribute('data-name');

            // update modal paragraph
            let modalParagraphStrong = modalAddBlockToZone.querySelector('.modal-body p.info strong')
            modalParagraphStrong.innerHTML = zoneName + ' (' + zoneIdentifier + ')';

            // hide/show blocks available
            let listOfBlockBtns = modalAddBlockToZone.querySelectorAll('.list-of-blocks button.block-item');
            Array.prototype.forEach.call(listOfBlockBtns, function(listOfBlockBtn) {

                // reference to add block into the zone identifier
                listOfBlockBtn.setAttribute('data-addintozone', zoneIdentifier);

                let blockZoneAvailablity = listOfBlockBtn.getAttribute('data-zones').split('|#|');
                if (blockZoneAvailablity.includes(zoneIdentifier)){
                    listOfBlockBtn.classList.remove('d-none');
                } else {
                    listOfBlockBtn.classList.add('d-none');
                }

            })

        })

        const addBlockToZoneBtns = modalAddBlockToZone.querySelectorAll('.modal-body .list-of-blocks button.block-item');
        Array.prototype.forEach.call(addBlockToZoneBtns, function(addBlockToZoneBtn) {

            addBlockToZoneBtn.addEventListener('click', function (event) {

                // get the button
                let addBtn = event.currentTarget;

                // get data
                let zoneIdentifier = addBtn.getAttribute('data-addintozone');
                let blockIdentifier = addBtn.getAttribute('data-identifier');

                // inject
                addBlocksToZones(zoneIdentifier, blockIdentifier, true);

                let bsModalAddBlockToZone = bootstrap.Modal.getOrCreateInstance(modalAddBlockToZone);
                bsModalAddBlockToZone.hide();

            })

        })

    }

    /**
     * Enable sorting blocks
     */
    const blockSectionsInZone = zoneSection.querySelectorAll('.mugopage-tabs-container .blocks-section')
    Array.prototype.forEach.call(blockSectionsInZone, function(blockSectionInZone) {
        new Sortable(blockSectionInZone, {
            animation: 500,
            ghostClass: 'accordion-item-ghost',
            handle: '.button-handler', // handle's class
            onEnd: function ( evt, originalEvent) {
                storeBlockConfiguration()
            },
        });
    })

    /**
     * Load database data into ui
     */
    const loadData = (data) => {

        if (typeof data.layout !== 'undefined'){

            // load active layout
            setActiveLayout(data.layout)

            if (typeof data.zones !== 'undefined'){

                Array.prototype.forEach.call(data.zones, function(zone) {

                    if (typeof zone.identifier !== 'undefined' && typeof zone.blocks !== 'undefined'){

                        Array.prototype.forEach.call(zone.blocks, function(block) {
                            if (typeof block.type.identifier !== 'undefined'){
                                addBlocksToZones(zone.identifier, block.type.identifier, false, block);
                            }
                        })

                    }

                })

            }

        }

        storeBlockConfiguration();

    }
    document.addEventListener("DOMContentLoaded", function() {
        if (ibexaTextAreaField.value != '') {
            let data = JSON.parse(ibexaTextAreaField.value);
            loadData(data);
        }
    })

    /**
     * Remove new-block class from block items when collapse expands
     */
    const blockAccordionTabs = zoneSection.querySelectorAll('.mugopage-tabs-container .accordion-collapse');
    Array.prototype.forEach.call(blockAccordionTabs, function(blockAccordionTab) {
        blockAccordionTab.addEventListener('show.bs.collapse', event => {
            let blocks = blockAccordionTab.querySelectorAll('.blocks-section .block-item.new-block');
            Array.prototype.forEach.call(blocks, function (block) {
                block.classList.remove('new-block');
            })
        })
    })

})