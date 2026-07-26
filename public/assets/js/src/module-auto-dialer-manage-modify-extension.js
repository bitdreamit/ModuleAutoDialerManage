/*
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 11 2018
 *
 */
const idUrl     = 'module-auto-dialer-manage';
const idForm    = 'extension-form';
let baseUrl = window.location.protocol + '//' + window.location.hostname;
if (window.location.port) {
	baseUrl += ':' + window.location.port;
}
/* global globalRootUrl, globalTranslate, Form, Config */
const ModuleAutoDialerManage = {
	$formObj: $('#'+idForm),
	$checkBoxes: $('#'+idForm+' .ui.checkbox'),
	$dropDowns: $('#'+idForm+' .ui.dropdown'),
	/**
	 * Field validation rules
	 * https://semantic-ui.com/behaviors/form.html
	 */
	validateRules: {
	},
	/**
	 * On page load we init some Semantic UI library
	 */
	initialize() {
		$('#content-frame').removeClass('segment');
		$('.ui.accordion').accordion();
		// инициализируем чекбоксы и выподающие менюшки
		ModuleAutoDialerManage.$checkBoxes.checkbox();
		ModuleAutoDialerManage.$dropDowns.dropdown();
		ModuleAutoDialerManage.initializeForm();
		$('.menu .item').tab();
	},

	calculatePageLength() {
		let rowHeight = ModuleAutoDialerManage.$pollingTable.find('tbody > tr').first().outerHeight();
		const windowHeight = window.innerHeight;
		const headerFooterHeight = 400 ;
		return Math.max(Math.floor((windowHeight - headerFooterHeight) / rowHeight), 5);
	},

	/**
	 * We can modify some data before form send
	 * @param settings
	 * @returns {*}
	 */
	cbBeforeSendForm(settings) {
		const result = settings;
		result.data = ModuleAutoDialerManage.$formObj.form('get values');
		return result;
	},
	/**
	 * Some actions after forms send
	 */
	cbAfterSendForm(response) {
		if(response.success){
			$('input[name="id"]').val(response.id);
			let newUrl = baseUrl + '/admin-cabinet/module-auto-dialer-manage/modifyExtension/'+response.id;
			window.history.pushState({path: newUrl}, '', newUrl);
		}
		Extensions.cbOnDataChanged();
	},
	/**
	 * Initialize form parameters
	 */
	initializeForm() {
		Form.$formObj = ModuleAutoDialerManage.$formObj;
		Form.url = `${globalRootUrl}${idUrl}/saveExtension`;
		Form.validateRules = ModuleAutoDialerManage.validateRules;
		Form.cbBeforeSendForm = ModuleAutoDialerManage.cbBeforeSendForm;
		Form.cbAfterSendForm = ModuleAutoDialerManage.cbAfterSendForm;
		Form.initialize();
	}
};

$(document).ready(() => {
	ModuleAutoDialerManage.initialize();
});

