describe('Plaudit Pre-endorsement - Plugin setup', function () {
    it('Enables Plaudit Pre-endorsement plugin', function () {
		cy.login('dbarnes', null, 'publicknowledge');

		cy.contains('a', 'Website').click();

		cy.waitJQuery();
		cy.get('#plugins-button').click();

		cy.get('input[id^=select-cell-plauditpreendorsementplugin]').check();
		cy.get('input[id^=select-cell-plauditpreendorsementplugin]').should('be.checked');
    });
});