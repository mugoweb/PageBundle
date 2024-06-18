// Complete SortableJS (with all plugins)
import Sortable from '../lib/sortablejs/modular/sortable.complete.esm.js';

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
 * Add animation class to sticky blocks
 * @type {Element}
 */
const observer = new IntersectionObserver(
    ([e]) => e.target.classList.toggle("is-pinned", e.intersectionRatio < 1),
    { threshold: [1] }
);

const actionBlockStickys = document.querySelectorAll(".action-block-sticky")
Array.prototype.forEach.call(actionBlockStickys, function(actionBlockSticky) {
    observer.observe(actionBlockSticky);
})


/**
 * Create new action
 */
// this function restores the page to the same status it has when the page loads
const resetPage = () => {

    const emptyItemBlock = document.getElementById('empty-item-block')
    const addNewItemBlock = document.getElementById('add-new-item-block')

    const bsCollapseEmptyItemBlock = bootstrap.Collapse.getOrCreateInstance(emptyItemBlock);
    bsCollapseEmptyItemBlock.show();

    const bsCollapseAddNewItemBlock = bootstrap.Collapse.getOrCreateInstance(addNewItemBlock);
    bsCollapseAddNewItemBlock.hide();

}

/**
 * Remove custom attribute form block form
 * @param event
 */
const removeCustomAttributeAction = (event) => {
    let item = event.currentTarget;
    // get the block
    const customAttributeBlock = item.parentNode.parentNode;
    //remove block
    customAttributeBlock.parentNode.removeChild(customAttributeBlock);
}

/**
 * Replace the custom attribute title to match the input field
 * @param event
 */
const replaceAttributeTitle = (event) => {
    const item = event.currentTarget;
    const accordionItem = item.closest('.accordion-item');
    if (accordionItem){
        let accordionTitle = accordionItem.querySelector('.accordion-header div.attribute-name');
		let attributeType = accordionTitle.getAttribute('data-type');
        accordionTitle.innerHTML = item.value + ' [' + attributeType + ']';
    }
}

/**
 * Creates or load a custom attribute in the block form
 */
const addBlockCustomAttribute = (attrType, attrData = [], collapsed = false) => {

    // define main elements
    const customAttributeSection = document.querySelector('.mugopage .mugopage-editorblock .edit-item-block .new .custom-attributes');
    const templateCustomNewAttribute = document.querySelector('#new-custom-attribute-template .new-custom-attribute');
	const templateCustomNewAttributeTypes = document.querySelector('#new-custom-attribute-template .new-custom-attribute-types');
	const templateCustomNewAttributeType = templateCustomNewAttributeTypes.querySelector('div[data-customattributetype="'+attrType+'"]')

	// check attribute type
	if(!templateCustomNewAttributeType){
		ibexa.helpers.notification.showErrorNotification('Unknow custom attribute type: ' + attrType);
		return false;
	}

	// prepare unique attribute id
	let now = new Date();
	const timestamp = now.getTime();
	const randomEntry = Math.floor(Math.random() * 999999);
	const nextCustomAttributeIndex = customAttributeSection.querySelectorAll('.new-custom-attribute').length + 1;
	const accordionId = 'accordion-randomid-'+timestamp+'-'+randomEntry+nextCustomAttributeIndex;

	// create the attribute element
	let newAttribute = templateCustomNewAttribute.cloneNode(true);

    // prepare new attribute
    newAttribute.querySelector('.accordion-header button.accordion-action').setAttribute('data-bs-target', '#'+accordionId);
    newAttribute.querySelector('.accordion-header button.accordion-action').setAttribute('aria-controls', accordionId);
    newAttribute.querySelector('div.accordion-collapse').setAttribute('id', accordionId);
	newAttribute.querySelector('.accordion-header .attribute-name').setAttribute('data-type', attrType);

	// get custom attribute type name
	let attrTypeName = templateCustomNewAttributeType.getAttribute('data-customattributename');

	// create and prepare the custom attribute field
	let newAttributeType = templateCustomNewAttributeType.cloneNode(true);
	let newAttributeFields = newAttributeType.querySelectorAll('*[data-id]');
	Array.prototype.forEach.call(newAttributeFields, function(newAttributeField) {

		// get the field name
		let fieldName = newAttributeField.getAttribute('data-id');

		// prepare the attrname
		let attrName = 'attr['+timestamp+'-'+nextCustomAttributeIndex+']';
		if (fieldName.slice(-2) != '[]'){
			attrName += '['+fieldName+']'; // regular fields
		} else {
			attrName += '['+fieldName.slice(0, -2)+'][]'; // array fields
		}

		// update the name attribute
		newAttributeField.setAttribute('name', attrName);

	})

	// load values
	if (attrData){

		Object.keys(attrData).forEach(function(key) {

			// get form field type
			var customFormField = newAttributeType.querySelector('[data-id="'+key+'"], [data-id="'+key+'[]"]');
			if (customFormField) {

				// update block name | To do: unify it in a single function
				if (key == 'name'){
					let typeId = newAttribute.querySelector('.accordion-header .attribute-name').getAttribute('data-type');
					newAttribute.querySelector('.accordion-header .attribute-name').innerHTML = attrData[key] + ' [' + typeId + ']';
				}

				switch (customFormField.nodeName){
					case 'INPUT':
                                        case 'TEXTAREA':
						customFormField.value = attrData[key];
						break;
					case 'SELECT':
						let customFormFieldOptions = customFormField.options;
						Array.prototype.forEach.call(customFormFieldOptions, function(customFormFieldOption) {
							if (attrData[key].includes(customFormFieldOption.value)){
								customFormFieldOption.selected = true;
							} else {
								customFormFieldOption.selected = false;
							}
						})
						break;
					default:
						ibexa.helpers.notification.showErrorNotification('Custom Attribute field type does not have a load case implemented: ' + customFormField.nodeName + '!');
						break;
				}

			} else {
				ibexa.helpers.notification.showErrorNotification('Custom Attribute field was not found: ' + key + '! The existing content can not be loaded.');
			}
		});

	}

	newAttribute.querySelector('.accordion-body').append(newAttributeType);

	// add events
	newAttribute.querySelector('.accordion-header button.btn-accordion.remove').addEventListener('click', removeCustomAttributeAction);
	newAttribute.querySelector('.accordion-body input[data-id="name"]').addEventListener('keyup', replaceAttributeTitle);

	// forces the custom attribute to appear collapsed on the page
	if (collapsed) {

		newAttribute.querySelector('.accordion-header button.accordion-action').classList.add('collapsed');
		newAttribute.querySelector('.accordion-header button.accordion-action').setAttribute('aria-expanded', "false");
		newAttribute.querySelector('div.accordion-collapse').classList.remove('show');

	}

	customAttributeSection.append(newAttribute);

}

/**
 * Prepares the form for a new zone
 */
const cleanZoneForm = () => {

    // reset the form
    let form = document.getElementById('mugopage-form');
    form.querySelector('input[name="name"]').value = '';
    form.querySelector('input[name="identifier"]').value = '';
    form.querySelector('input[name="actiontype"]').value = ITEM_ACTION_NEW;

    form.querySelector('input[name="identifier"]').setAttribute('type', 'text');

    form.querySelector('.identifier').innerHTML = '';
    form.querySelector('.identifier').classList.add('d-none');

    form.querySelector('.action-block  .action-title span').innerHTML = 'New';

    form.querySelector('#btn-delete-item').classList.add('d-none');

}

/**
 * Prepares the form for a new layout
 */
const cleanLayoutForm = () => {

    // reset the form
    let form = document.getElementById('mugopage-form');
    form.querySelector('input[name="name"]').value = '';
    form.querySelector('input[name="identifier"]').value = '';
    form.querySelector('input[name="template"]').value = '';
    let contentTypeOptions = form.querySelector('select[name="contenttypes[]"]').options;
    Array.prototype.forEach.call(contentTypeOptions, function(contentTypeOption) {
        contentTypeOption.selected = false;
    })
    let zoneOptions = form.querySelector('select[name="zones[]"]').options;
    Array.prototype.forEach.call(zoneOptions, function(zoneOption) {
        zoneOption.selected = false;
    })

    form.querySelector('input[name="actiontype"]').value = ITEM_ACTION_NEW;

    form.querySelector('input[name="identifier"]').setAttribute('type', 'text');

    form.querySelector('.identifier').innerHTML = '';
    form.querySelector('.identifier').classList.add('d-none');

    form.querySelector('.action-block  .action-title span').innerHTML = 'New';

    form.querySelector('#btn-delete-item').classList.add('d-none');

}

/**
 * Prepares the form for a new block
 */
const cleanBlockForm = () => {

    // reset the form
    let form = document.getElementById('mugopage-form');
    form.querySelector('input[name="name"]').value = '';
    form.querySelector('input[name="identifier"]').value = '';
    form.querySelector('textarea[name="description"]').value = '';
    form.querySelector('input[name="template"]').value = '';

    let zoneOptions = form.querySelector('select[name="zones[]"]').options;
    Array.prototype.forEach.call(zoneOptions, function(zoneOption) {
        zoneOption.selected = false;
    })

    form.querySelector('div.custom-attributes').innerHTML = '';

    form.querySelector('input[name="actiontype"]').value = ITEM_ACTION_NEW;

    form.querySelector('input[name="identifier"]').setAttribute('type', 'text');

    form.querySelector('.identifier').innerHTML = '';
    form.querySelector('.identifier').classList.add('d-none');

    form.querySelector('.action-block  .action-title span').innerHTML = 'New';

    form.querySelector('#btn-delete-item').classList.add('d-none');

}

/**
 * Load the zone data into the edit form
 * @param item
 */
const loadZoneForm = (item) => {

    let name = item.getAttribute('data-name');
    let identifier = item.getAttribute('data-identifier');

    // update
    let form = document.getElementById('mugopage-form');
    form.querySelector('input[name="name"]').value = name;
    form.querySelector('input[name="identifier"]').value = identifier;

    form.querySelector('input[name="actiontype"]').value = ITEM_ACTION_EDIT;

    form.querySelector('input[name="identifier"]').setAttribute('type', 'hidden');

    form.querySelector('.identifier').innerHTML = identifier;
    form.querySelector('.identifier').classList.remove('d-none');

    form.querySelector('.action-block  .action-title span').innerHTML = 'Edit';

    form.querySelector('#btn-delete-item').classList.remove('d-none');

}

/**
 * Load the layout data into the edit form
 * @param item
 */
const loadLayoutForm = (item) => {

    let name = item.getAttribute('data-name');
    let identifier = item.getAttribute('data-identifier');
    let template = item.getAttribute('data-template');
    let contentTypes = item.getAttribute('data-contenttypes').split('|#|');
    let zones = item.getAttribute('data-zones').split('|#|');

    // update
    let form = document.getElementById('mugopage-form');
    form.querySelector('input[name="name"]').value = name;
    form.querySelector('input[name="identifier"]').value = identifier;
    form.querySelector('input[name="template"]').value = template;
    let contentTypeOptions = form.querySelector('select[name="contenttypes[]"]').options;
    Array.prototype.forEach.call(contentTypeOptions, function(contentTypeOption) {
        if (contentTypes.includes(contentTypeOption.value)){
            contentTypeOption.selected = true;
        } else {
            contentTypeOption.selected = false;
        }
    })
    let zoneOptions = form.querySelector('select[name="zones[]"]').options;
    Array.prototype.forEach.call(zoneOptions, function(zoneOption) {
        if (zones.includes(zoneOption.value)){
            zoneOption.selected = true;
        } else {
            zoneOption.selected = false;
        }
    })

    form.querySelector('input[name="actiontype"]').value = ITEM_ACTION_EDIT;

    form.querySelector('input[name="identifier"]').setAttribute('type', 'hidden');

    form.querySelector('.identifier').innerHTML = identifier;
    form.querySelector('.identifier').classList.remove('d-none');

    form.querySelector('.action-block  .action-title span').innerHTML = 'Edit';

    form.querySelector('#btn-delete-item').classList.remove('d-none');

}

/**
 * Load the layout data into the edit form
 * @param item
 */
const loadBlockForm = (item) => {

    let name = item.getAttribute('data-name');
    let identifier = item.getAttribute('data-identifier');
    let description = item.getAttribute('data-description');
    let template = item.getAttribute('data-template');
    let zones = item.getAttribute('data-zones').split('|#|');
    let attr = JSON.parse(item.getAttribute('data-attr'));

    // update
    let form = document.getElementById('mugopage-form');
    form.querySelector('input[name="name"]').value = name;
    form.querySelector('input[name="identifier"]').value = identifier;
    form.querySelector('textarea[name="description"]').value = description;
    form.querySelector('input[name="template"]').value = template;

    let zoneOptions = form.querySelector('select[name="zones[]"]').options;
    Array.prototype.forEach.call(zoneOptions, function(zoneOption) {
        if (zones.includes(zoneOption.value)){
            zoneOption.selected = true;
        } else {
            zoneOption.selected = false;
        }
    })

    // create the custom attributes
    document.querySelector('.mugopage .mugopage-editorblock .edit-item-block .new .custom-attributes').innerHTML = '';
	Object.keys(attr).forEach(function(key) {
		addBlockCustomAttribute(attr[key].type, attr[key], false);
	});

    form.querySelector('input[name="actiontype"]').value = ITEM_ACTION_EDIT;

    form.querySelector('input[name="identifier"]').setAttribute('type', 'hidden');

    form.querySelector('.identifier').innerHTML = identifier;
    form.querySelector('.identifier').classList.remove('d-none');

    form.querySelector('.action-block  .action-title span').innerHTML = 'Edit';

    form.querySelector('#btn-delete-item').classList.remove('d-none');

}

/**
 * Handles the new and edit content
 * It is triggered by Create new or items buttons in the sidebar list
 */
const loadEditCreateItem = (event) => {

    const emptyItemBlock = document.getElementById('empty-item-block')
    const addNewItemBlock = document.getElementById('add-new-item-block')

    const bsCollapseEmptyItemBlock = bootstrap.Collapse.getOrCreateInstance(emptyItemBlock);
    bsCollapseEmptyItemBlock.hide();

    const bsCollapseAddNewItemBlock = bootstrap.Collapse.getOrCreateInstance(addNewItemBlock);
    bsCollapseAddNewItemBlock.show();

    let item = event.currentTarget;

    // check if it is a new item or existing item
    let action = item.getAttribute('data-action');
    let type = item.getAttribute('data-type');

    if( action == ITEM_ACTION_NEW){

        switch (type) {
            case ITEM_TYPE_ZONE:
                cleanZoneForm();
                break;
            case ITEM_TYPE_LAYOUT:
                cleanLayoutForm();
                break;
            case ITEM_TYPE_BLOCK:
                cleanBlockForm();
                break;
            default:
                // uses ibexa notification to show status
                ibexa.helpers.notification.showErrorNotification('Item type has no create handler!');
        }

    } else if ( action == ITEM_ACTION_EDIT){

        switch (type) {
            case ITEM_TYPE_ZONE:
                loadZoneForm(item);
                break;
            case ITEM_TYPE_LAYOUT:
                loadLayoutForm(item);
                break;
            case ITEM_TYPE_BLOCK:
                loadBlockForm(item);
                break;
            default:
                // uses ibexa notification to show status
                ibexa.helpers.notification.showErrorNotification('Item type has no load edit handler!');
        }

    } else {
        // uses ibexa notification to show status
        ibexa.helpers.notification.showErrorNotification('Item has no action handler!');
    }

    // remove active class from items
    let hasActiveClassBtns = document.querySelectorAll('.mugopage .mugopage-sidebarlist .items .item.active');
    Array.prototype.forEach.call(hasActiveClassBtns, function(hasActiveClassBtn) {
        hasActiveClassBtn.classList.remove('active');
    })
    item.classList.add('active');

};
const createNewButtons = document.querySelectorAll(".mugopage-sidebarlist .list .new button")
Array.prototype.forEach.call(createNewButtons, function(createNewButton) {
    createNewButton.addEventListener('click', loadEditCreateItem);
})

/**
 * Edit item action
 */
const prepareEditItem = () => {

}
const editButtons = document.querySelectorAll(".mugopage-sidebarlist .list .items button")
Array.prototype.forEach.call(editButtons, function(editButton) {
    editButton.addEventListener('click', loadEditCreateItem);
})

/**
 * Creates/updates items in the list according to predefined type
 * @param data
 */
const createOrUpdateItemInList = (data) => {

    let sidebarList = document.querySelector('.mugopage .mugopage-sidebarlist');
    let listItems = sidebarList.querySelector('.list .items');
    let itemOriginal = sidebarList.querySelector('.list .ref .item');
    let form = document.getElementById('mugopage-form');
    let refinedData = {};

    switch (data.itemtype) {
        case ITEM_TYPE_ZONE:
            // refine zone data
            refinedData['name'] = form.querySelector('input[name="name"]').value;
            refinedData['identifier'] = form.querySelector('input[name="identifier"]').value;
            refinedData['type'] = ITEM_TYPE_ZONE;
            break;
        case ITEM_TYPE_LAYOUT:
            // refine layout data
            refinedData['name'] = form.querySelector('input[name="name"]').value;
            refinedData['identifier'] = form.querySelector('input[name="identifier"]').value;
            refinedData['template'] = form.querySelector('input[name="template"]').value;

            let selectedContentTypeOptionsLayout = form.querySelector('select[name="contenttypes[]"]').selectedOptions;
            let selectedContentTypeValuesLayout = [];
            Array.prototype.forEach.call(selectedContentTypeOptionsLayout, function(selectedContentTypeOption) {
                selectedContentTypeValuesLayout.push(selectedContentTypeOption.value);
            })
            refinedData['contenttypes'] = selectedContentTypeValuesLayout.join('|#|');

            let selectedZoneOptionsLayout = form.querySelector('select[name="zones[]"]').selectedOptions;
            let selectedZoneValuesLayout = [];
            Array.prototype.forEach.call(selectedZoneOptionsLayout, function(selectedZoneOption) {
                selectedZoneValuesLayout.push(selectedZoneOption.value);
            })
            refinedData['zones'] = selectedZoneValuesLayout.join('|#|');

            refinedData['type'] = ITEM_TYPE_LAYOUT;
            break;
		case ITEM_TYPE_BLOCK:
            // refine layout data
            refinedData['name'] = form.querySelector('input[name="name"]').value;
            refinedData['identifier'] = form.querySelector('input[name="identifier"]').value;
            refinedData['description'] = form.querySelector('textarea[name="description"]').value;
            refinedData['template'] = form.querySelector('input[name="template"]').value;

			// refine custom attributes
			let formData = new FormData(form);
			let attrData = {};

			console.log('formData here');
			formData.forEach((value, key) => {

				// only for custom attribute fields
				if(key.slice(0,5) == 'attr['){

					let keySplit = key.split('[');
					switch(keySplit.length){
						case 3:
							// regular field
							if(!Reflect.has(attrData, keySplit[1].slice(0,-1))){
								attrData[keySplit[1].slice(0,-1)] = {};
							}
							attrData[keySplit[1].slice(0,-1)][keySplit[2].slice(0,-1)] = value;
							break;
						case 4:
							// array field
							if(!Reflect.has(attrData, keySplit[1].slice(0,-1))){
								attrData[keySplit[1].slice(0,-1)] = {};
							}
							if(!Reflect.has(attrData[keySplit[1].slice(0,-1)], keySplit[2].slice(0,-1))){
								attrData[keySplit[1].slice(0,-1)][keySplit[2].slice(0,-1)] = [];
							}
							attrData[keySplit[1].slice(0,-1)][keySplit[2].slice(0,-1)].push(value);
							break;
						default:
							ibexa.helpers.notification.showWarningNotification('The custom fields were not properly loaded on the page! Reload the page to prevent any data loss.');
							break;
					}

				}

			})

			console.log(attrData);
			console.log(JSON.stringify(attrData));

			refinedData['attr'] = JSON.stringify(attrData);

            let selectedZoneOptionsBlock = form.querySelector('select[name="zones[]"]').selectedOptions;
            let selectedZoneValuesBlock = [];
            Array.prototype.forEach.call(selectedZoneOptionsBlock, function(selectedZoneOption) {
                selectedZoneValuesBlock.push(selectedZoneOption.value);
            })
            refinedData['zones'] = selectedZoneValuesBlock.join('|#|');

            refinedData['type'] = ITEM_TYPE_BLOCK;
            break;
        default:
            // uses ibexa notification to show status
            ibexa.helpers.notification.showWarningNotification(' The request does not have a proper update list method');
    }

    if (Object.keys(refinedData).length  > 1) {

        // remove any previous item
        let prevItem = listItems.querySelector('.item[data-identifier="' + refinedData.identifier + '"]');
        if (prevItem) {
            prevItem.parentNode.removeChild(prevItem);
        }

        // create new item
        let newItem = itemOriginal.cloneNode(true);
        newItem.querySelector('.item-main span').innerHTML = refinedData.name;
        newItem.querySelector('.item-secondary span').innerHTML = refinedData.identifier;

        // set the attributes
        for (let key of Object.keys(refinedData)) {
            newItem.setAttribute('data-'+key, refinedData[key]);
        }
        newItem.setAttribute('data-action', ITEM_ACTION_EDIT);

        // add click event
        newItem.addEventListener('click', loadEditCreateItem);

        // add new class
        newItem.classList.add('new-item');

        listItems.prepend(newItem);

    }

}

/**
 * Save Action with Ajax
 */
let mugopageForm = document.getElementById('mugopage-form')
if (mugopageForm){
    mugopageForm.addEventListener('submit', function (event) {
        var data = this;
        fetch(data.getAttribute('action'), {
            method: data.getAttribute('method'),
            body: new FormData(data)
        })
            .then(response => response.json())
            .then((value) => {

                switch (value.type) {
                    case 'error':
                        // uses ibexa notification to show status
                        ibexa.helpers.notification.showErrorNotification(value.message);
                        break;
                    case 'warning':
                        // uses ibexa notification to show status
                        ibexa.helpers.notification.showWarningNotification(value.message);
                        break;
                    case 'success':
                        // uses ibexa notification to show status
                        ibexa.helpers.notification.showSuccessNotification(value.message);
                        resetPage();

                        if (typeof value.itemtype != "undefined") {

                            createOrUpdateItemInList(value);

                        } else {
                            // uses ibexa notification to show status
                            ibexa.helpers.notification.showWarningNotification('The item content type is not available!');
                        }

                        break;
                    default:
                        // uses ibexa notification to show status
                        ibexa.helpers.notification.showErrorNotification(value.message);
                }

            })
            .catch((err) => {
                // uses ibexa notification to show status
                ibexa.helpers.notification.showErrorNotification('There is an error on your request!');
            });
        event.preventDefault();
    });
}

/**
 * Delete Confirmation Modal actions
 */
const modalConfirmDelete = document.getElementById('modal-confirm-delete');
if (modalConfirmDelete){

    let btnConfirmDelete = modalConfirmDelete.querySelector('#btn-confirm-delete');

    modalConfirmDelete.addEventListener('show.bs.modal', event => {

        let form = document.getElementById('mugopage-form');

        // get item data
        let type = form.getAttribute('data-type');
        let name = form.querySelector('input[name="name"]').value;
        let identifier = form.querySelector('input[name="identifier"]').value;

        modalConfirmDelete.querySelector('.name').innerHTML = name;
        modalConfirmDelete.querySelector('.identifier').innerHTML = identifier;

    })

    btnConfirmDelete.addEventListener('click', function(){

        let action = this.getAttribute('data-deleteurl');

        if (action === null) {
            // uses ibexa notification to show status
            ibexa.helpers.notification.showErrorNotification('There is an error on your delete request| Missing delete URL');

            return;
        }

        let form = document.getElementById('mugopage-form');

        fetch(action, {
            method: form.getAttribute('method'),
            body: new FormData(form)
        })
            .then(response => response.json())
            .then((value) => {

                switch (value.type) {
                    case 'error':
                        // uses ibexa notification to show status
                        ibexa.helpers.notification.showErrorNotification(value.message);
                        break;
                    case 'warning':
                        // uses ibexa notification to show status
                        ibexa.helpers.notification.showWarningNotification(value.message);
                        break;
                    case 'success':
                        // uses ibexa notification to show status
                        ibexa.helpers.notification.showSuccessNotification(value.message);
                        resetPage();

                        // close modal
                        let bsModalConfirmDelete = bootstrap.Modal.getOrCreateInstance(modalConfirmDelete);
                        bsModalConfirmDelete.hide();

                        // remove item from list
                        let sidebarList = document.querySelector('.mugopage .mugopage-sidebarlist');
                        let listItems = sidebarList.querySelector('.list .items');

                        // get item data
                        let identifier = value.itemidentifier;

                        // remove the item from the list
                        let prevItem = listItems.querySelector('.item[data-identifier="'+identifier+'"]');
                        if (prevItem){
                            prevItem.parentNode.removeChild(prevItem);
                        }

                        break;
                    default:
                        // uses ibexa notification to show status
                        ibexa.helpers.notification.showErrorNotification(value.message);
                }

            })
            .catch((err) => {
                // uses ibexa notification to show status
                ibexa.helpers.notification.showErrorNotification('There is an error on your delete request! Wrong response');
            });

    })

}

/**
 * Add custom attribute to block config
 */
const btnAddCustomAttribute = document.getElementById('add-new-custom-attribute');
if (btnAddCustomAttribute){

    const modalCustomAttributeType = document.getElementById('modal-select-custom-attribute-type')

    // add click event to custom attribute type on modal
    const btnCustomAttributeTypes = modalCustomAttributeType.querySelectorAll('.modal-body .attribute-type-list button');
    if (btnCustomAttributeTypes){

        Array.prototype.forEach.call(btnCustomAttributeTypes, function(btnCustomAttributeType) {

            // add click event to add new custom attribute button
            btnCustomAttributeType.addEventListener('click', () => {

                // get type
                let attributeType = btnCustomAttributeType.getAttribute('data-customattributetype');

                // add block to section
                addBlockCustomAttribute(attributeType);

                // hide modal
                let bsModalCustomAttributeType = bootstrap.Modal.getOrCreateInstance(modalCustomAttributeType);
                bsModalCustomAttributeType.hide();

            });

        })
    }

    const customAttributeSectionOnForm = document.querySelector('.mugopage .mugopage-editorblock .edit-item-block .new .custom-attributes');

    new Sortable(customAttributeSectionOnForm, {
        animation: 500,
        ghostClass: 'accordion-item-ghost',
        handle: '.button-handler', // handle's class
    });

}