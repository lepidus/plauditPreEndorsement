import '../support/commands.js';

describe("Plaudit Pre-Endorsement Plugin - Workflow features", function() {
    let submissionTitle = "Killers of the Flower Moon";

    it("Endorsement information in workflow tab", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);
        
        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();
        cy.get('input[name="endorserNameWorkflow"]').should('have.value', 'Bong Joon-ho');
        cy.get('input[name="endorserEmailWorkflow"]').should('have.value', 'bong.joon-ho@email.kr');
        cy.contains('The endorsement has not yet been confirmed by the endorser');
        cy.contains("1 endorsement confirmation e-mail has been sent to the endorser");
        cy.get('#plauditPreEndorsement-button .pkpBadge:contains("1")');
    });
    it("E-mail sendings counting in workflow tab", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);
        
        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();
        cy.contains("1 endorsement confirmation e-mail has been sent to the endorser");
        cy.get("#plauditPreEndorsement").contains("Save").click();
        
        cy.reload();
        cy.contains("2 endorsement confirmation e-mails have been sent to the endorser");
        cy.get('input[name="endorserNameWorkflow"]').clear().type("Lady Diana", { delay: 0 });
        cy.get('input[name="endorserEmailWorkflow"]').clear().type("lady.diana@gmail.com", { delay: 0 });
        cy.get("#plauditPreEndorsement").contains("Save").click();
        
        cy.reload();
        cy.contains("1 endorsement confirmation e-mail has been sent to the endorser");
    });
    it("Endorsement removal", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);

        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();
        cy.contains('button', 'Remove endorsement').should('not.exist');
        cy.logout();

        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionTitle);
        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();
        cy.contains('button', 'Remove endorsement').click();
        cy.on('window:confirm', () => true);
        cy.reload();

        cy.get('input[name="endorserNameWorkflow"]').should('have.value', '');
        cy.get('input[name="endorserEmailWorkflow"]').should('have.value', '');
        cy.contains('The endorsement has not yet been confirmed by the endorser').should('not.exist');
        cy.contains("1 endorsement confirmation e-mail has been sent to the endorser").should('not.exist');
        cy.get('#plauditPreEndorsement-button .pkpBadge:contains("0")');
    });
    it("Endorsement adding on workflow", function() {
        let newEndorserName = 'Francis Ford Coppola';
        let newEndorserEmail = 'francis.coppola@hollywood.com';
        
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);

        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();

        cy.get('input[name="endorserNameWorkflow"]').clear().type(newEndorserName, { delay: 0 });
        cy.get('input[name="endorserEmailWorkflow"]').clear().type(newEndorserEmail, { delay: 0 });
        cy.get("#plauditPreEndorsement").contains("Save").click();
        
        cy.reload();
        cy.get('input[name="endorserNameWorkflow"]').should('have.value', newEndorserName);
        cy.get('input[name="endorserEmailWorkflow"]').should('have.value', newEndorserEmail);
        cy.contains('The endorsement has not yet been confirmed by the endorser');
        cy.contains("1 endorsement confirmation e-mail has been sent to the endorser");
        cy.get('#plauditPreEndorsement-button .pkpBadge:contains("1")');
    });
    it("Endorsement actions are written in submission's Activity Log", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);
        cy.contains('button', 'Activity Log').click();

        cy.contains('An endorsement confirmation e-mail has been sent to Bong Joon-ho (bong.joon-ho@email.kr)');
        cy.contains('An endorsement confirmation e-mail has been sent to Lady Diana (lady.diana@gmail.com)');
        cy.contains('The submission endorsement has been removed');
        cy.contains('An endorsement confirmation e-mail has been sent to Francis Ford Coppola (francis.coppola@hollywood.com)');
    });
});