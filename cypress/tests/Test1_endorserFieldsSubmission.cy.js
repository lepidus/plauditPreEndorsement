import '../support/commands.js';

function beginSubmission(submissionData) {
    cy.get('input[name="locale"][value="en"]').click();
    cy.setTinyMceContent('startSubmission-title-control', submissionData.title);
    
    cy.get('input[name="submissionRequirements"]').check();
    cy.get('input[name="privacyConsent"]').check();
    cy.contains('button', 'Begin Submission').click();
}

describe("Plaudit Pre-Endorsement Plugin - Endorser fields in submission wizard", function() {
    let submissionData;
    let dummyPdf;
    let endorsers;
    
    before(function() {
        Cypress.config('defaultCommandTimeout', 4000);
        submissionData = {
            title: "Killers of the Flower Moon",
			abstract: 'A series of murders starts among native americans',
		};
        dummyPdf = {
            'file': 'dummy.pdf',
            'fileName': 'dummy.pdf',
            'mimeType': 'application/pdf',
            'genre': 'Preprint Text'
        };
        endorsers = {
            invalidEmail: {
                name: 'John Wayne',
                email: 'john.wayne.email'
            },
            isAuthor: {
                name: 'Catherine Kwantes',
                email: 'ckwantes@mailinator.com'
            },
            correct: {
                name: 'Bong Joon-ho',
                email: 'bong.joon-ho@email.kr'
            }
        };
    });
    
    it("Checks endorsement fields and validates endorser email", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.get('div#myQueue a:contains("New Submission")').click();

        beginSubmission(submissionData);
        cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract);
        
        cy.contains('h2', 'Endorsement');
        cy.contains('Do you have the endorsement of an experienced researcher in the field of knowledge of the manuscript?');
        cy.contains('If yes, please provide the name and e-mail address of the endorsing researcher. Endorsements can significantly speed up the moderation process.');
        cy.contains('The endorsement cannot be given by one of the authors of the manuscript.');

        cy.get('input[name="endorserName"]').clear().type(endorsers.invalidEmail.name, {delay: 0});
        cy.get('input[name="endorserEmail"]').clear().type(endorsers.invalidEmail.email, {delay: 0});
        cy.contains('button', 'Continue').click();
        
        cy.addSubmissionGalleys([dummyPdf]);
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
        
        cy.contains('Please enter a valid endorser e-mail');
    });
    it("Validates endorser is not an author of the submission", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
        
        cy.get('input[name="endorserName"]').clear().type(endorsers.isAuthor.name, {delay: 0});
        cy.get('input[name="endorserEmail"]').clear().type(endorsers.isAuthor.email, {delay: 0});
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
        
        cy.contains('The endorsement cannot be given by any of the authors of the manuscript');
    });
    it("Finishes submission with correct endorsement", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
        
        cy.get('input[name="endorserName"]').clear().type(endorsers.correct.name, {delay: 0});
        cy.get('input[name="endorserEmail"]').clear().type(endorsers.correct.email, {delay: 0});
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
        
        cy.contains('button', 'Submit').click();
        cy.get('.modal__panel:visible').within(() => {
            cy.contains('button', 'Submit').click();
        });
        cy.waitJQuery();
        cy.contains('h1', 'Submission complete');
    });
});