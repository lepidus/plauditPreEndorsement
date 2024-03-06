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

		cy.get('#orcidAPIPath').select('Public Sandbox');
		cy.get('input[name="orcidClientId"]').clear().type(Cypress.env('orcidClientId'), {delay: 0});
		cy.get('input[name="orcidClientSecret"]').clear().type(Cypress.env('orcidClientSecret'), {delay: 0});
		cy.get('input[name="plauditAPISecret"]').clear().type(dummyPlauditApiSecret, {delay: 0});

		cy.get('#plauditPreEndorsementSettingsForm button:contains("OK")').click();
		cy.contains('Please configure the ORCID API access for use in pulling ORCID profile information').should('not.exist');
	});
});