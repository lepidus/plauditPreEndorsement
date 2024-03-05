describe('Plaudit Pre-endorsement - Plugin setup', function () {
    it('Enables Plaudit Pre-endorsement plugin', function () {
		cy.login('dbarnes', null, 'publicknowledge');

		cy.contains('a', 'Website').click();

		cy.waitJQuery();
		cy.get('#plugins-button').click();

		cy.get('input[id^=select-cell-plauditpreendorsementplugin]').check();
		cy.get('input[id^=select-cell-plauditpreendorsementplugin]').should('be.checked');
    });
	it('Configures plugin', function() {
		const pluginRowId = 'component-grid-settings-plugins-settingsplugingrid-category-generic-row-plauditpreendorsementplugin';
		const dummyPlauditApiSecret = 'dummy_plaudit_api_secret';

		cy.login('dbarnes', null, 'publicknowledge');
		cy.contains('a', 'Website').click();

		cy.waitJQuery();
		cy.get('#plugins-button').click();
		cy.get('tr#' + pluginRowId + ' a.show_extras').click();
		cy.get('a[id^=' + pluginRowId + '-settings-button]').click();

		cy.get('#orcidAPIPath').select('Member Sandbox');
		cy.get('#orcidClientId').clear().type(Cypress.env('orcidClientId'), {delay: 0});
		cy.get('#orcidClientSecret').clear().type(Cypress.env('orcidClientSecret'), {delay: 0});
		cy.get('#plauditAPISecret').clear().type(dummyPlauditApiSecret, {delay: 0});

		cy.get('#plauditPreEndorsementSettingsForm button:contains("OK")').click();
		cy.get('div:contains("Your changes have been saved.")');
	});
});