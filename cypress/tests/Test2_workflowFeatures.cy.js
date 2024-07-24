import '../support/commands.js';

describe("Plaudit Pre-Endorsement Plugin - Workflow features", function() {
    let submissionTitle = "Killers of the Flower Moon";

    it("Endorsement information in workflow tab", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);
        
        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();
        cy.contains('Bong Joon-ho');
        cy.contains('bong.joon-ho@email.kr');
        cy.contains('DummyEndorsement');
        cy.contains('DummyEndorsement@mailinator.com');
        cy.contains('Awaiting confirmation');
        cy.get('#plauditPreEndorsement-button .pkpBadge:contains("2")');
    });

    it("Endorsement removal", function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionTitle);
        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();
        cy.get('[id*="component-plugins-generic-plauditpreendorsement-controllers-grid-endorsementgrid-row-"] > .first_column > .show_extras').first().click();
        cy.get('[id*="component-plugins-generic-plauditpreendorsement-controllers-grid-endorsementgrid-row-"][id*="-delete-button-"]').first().click();
        cy.get('.ok').click();
        cy.reload();

        cy.contains('Bong Joon-ho').should('not.exist');
        cy.contains("bong.joon-ho@email.kr").should('not.exist');
        cy.get('#plauditPreEndorsement-button .pkpBadge:contains("1")');
    });

    it("Endorsement adding on workflow", function() {
        let newEndorsementName = 'Francis Ford Coppola';
        let newEndorsementEmail = 'francis.coppola@hollywood.com';
        
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);

        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();

        cy.get('a[id^="component-plugins-generic-plauditpreendorsement-controllers-grid-endorsementgrid-addEndorsement-button-"]').contains("Add").click(); 
        cy.get('input[name="endorserName"]').clear().type(newEndorsementName, {delay: 0});
        cy.get('input[name="endorserEmail"]').clear().type(newEndorsementEmail, {delay: 0});
        cy.get('form[id="endorsementForm"]').find('button[id^="submitFormButton-"]').click();
        
        cy.reload();
        cy.contains(newEndorsementName);
        cy.contains(newEndorsementEmail);
        cy.get('#plauditPreEndorsement-button .pkpBadge:contains("2")');
    });
    
    it("Endorsement actions are written in submission's Activity Log", function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionTitle);
        cy.contains('button', 'Activity Log').click();

        cy.contains('An endorsement confirmation e-mail has been sent to Bong Joon-ho (bong.joon-ho@email.kr)');
        cy.contains('A submission endorsement has been removed: Bong Joon-ho (bong.joon-ho@email.kr)');
        cy.contains('An endorsement confirmation e-mail has been sent to Francis Ford Coppola (francis.coppola@hollywood.com)');
    });

    it("Check endorsements emails", function() {
        cy.visit('localhost:8025');
        cy.contains('Ramiro Vaca');
        cy.contains('DummyEndorsement@mailinator.com');
        cy.contains('bong.joon-ho@email.kr');
        cy.contains('francis.coppola@hollywood.com');
        cy.contains('Endorsement confirmation');
    });
});