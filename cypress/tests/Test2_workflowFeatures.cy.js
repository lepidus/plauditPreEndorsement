import '../support/commands.js';

describe("Plaudit Pre-Endorsement Plugin - Workflow features", function() {
    let submissionTitle = "Killers of the Flower Moon";

    it("Endorsement information in workflow menu", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);

        cy.contains('Pre-Endorsement').click();
        cy.contains('Bong Joon-ho');
        cy.contains('bong.joon-ho@email.kr');
        cy.contains('DummyEndorsement');
        cy.contains('DummyEndorsement@mailinator.com');
        cy.contains('Awaiting confirmation');
    });

    it("Endorsement removal", function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionTitle);
        cy.contains('Pre-Endorsement').click();

        cy.contains('tr', 'Bong Joon-ho').within(() => {
            cy.contains('button', 'Delete').click();
        });
        cy.contains('button', 'Yes').click();

        cy.contains('Bong Joon-ho').should('not.exist');
        cy.contains("bong.joon-ho@email.kr").should('not.exist');
    });

    it("Endorsement adding on workflow", function() {
        let newEndorsementName = 'Francis Ford Coppola';
        let newEndorsementEmail = 'francis.coppola@hollywood.com';

        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);

        cy.contains('Pre-Endorsement').click();

        cy.contains('button', 'Add').click();
        cy.get('input#endorserName').clear().type(newEndorsementName, {delay: 0});
        cy.get('input#endorserEmail').clear().type(newEndorsementEmail, {delay: 0});
        cy.contains('button', 'Save').click();

        cy.contains(newEndorsementName);
        cy.contains(newEndorsementEmail);
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
