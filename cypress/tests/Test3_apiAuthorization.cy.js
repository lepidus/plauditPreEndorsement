import '../support/commands.js';

describe("Plaudit Pre-Endorsement Plugin - Authorization of the endorsements API", function() {
    const apiUrl = 'index.php/publicknowledge/api/v1';
    const submissionTitle = "Killers of the Flower Moon";

    function getEndorsements(submissionId) {
        return cy.request({
            url: `${apiUrl}/endorsements/${submissionId}`,
            method: 'GET',
            failOnStatusCode: false
        });
    }

    function sendEndorsement(submissionId, endorsementId, csrfToken) {
        return cy.request({
            url: `${apiUrl}/endorsements/${submissionId}/${endorsementId}/send`,
            method: 'POST',
            headers: {
                'X-Csrf-Token': csrfToken
            },
            failOnStatusCode: false
        });
    }

    function loadSubmissionAndEndorsementIds() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.request(`${apiUrl}/submissions?count=100`).then((response) => {
            const submission = response.body.items.find((item) => JSON.stringify(item.publications).includes(submissionTitle));
            expect(submission, submissionTitle).to.exist;
            cy.wrap(submission.id).as('submissionId');
        });
        cy.get('@submissionId').then((submissionId) => {
            getEndorsements(submissionId).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.items).to.not.be.empty;
                cy.wrap(response.body.items[0].id).as('endorsementId');
            });
        });
        cy.logout();
    }

    it("Allows the submitting author but not to send endorsements to Plaudit", function() {
        loadSubmissionAndEndorsementIds();

        cy.login('ckwantes', null, 'publicknowledge');
        cy.getCsrfToken();
        cy.get('@submissionId').then((submissionId) => {
            getEndorsements(submissionId).its('status').should('eq', 200);
            cy.get('@endorsementId').then((endorsementId) => {
                cy.get('@csrfToken').then((csrfToken) => {
                    sendEndorsement(submissionId, endorsementId, csrfToken)
                        .its('status').should('be.oneOf', [401, 403]);
                });
            });
        });
    });

    it("Denies an author of other submissions", function() {
        loadSubmissionAndEndorsementIds();

        cy.login('ccorino', null, 'publicknowledge');
        cy.get('@submissionId').then((submissionId) => {
            getEndorsements(submissionId).its('status').should('be.oneOf', [401, 403]);
        });
    });

    it("Denies a moderator not assigned to the submission", function() {
        loadSubmissionAndEndorsementIds();

        cy.login('minoue', null, 'publicknowledge');
        cy.getCsrfToken();
        cy.get('@submissionId').then((submissionId) => {
            getEndorsements(submissionId).its('status').should('be.oneOf', [401, 403]);
            cy.get('@endorsementId').then((endorsementId) => {
                cy.get('@csrfToken').then((csrfToken) => {
                    sendEndorsement(submissionId, endorsementId, csrfToken)
                        .its('status').should('be.oneOf', [401, 403]);
                });
            });
        });
    });
});
