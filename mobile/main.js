// Script of the Moodle app page of mod_peerreview. It runs in the context of the page component, so `this` is the page.
// Data and texts come from \mod_peerreview\output\mobile::build_state() (see main.html). Uses the app's site object for
// web service calls (mod_peerreview_get_review, mod_peerreview_save_review, mod_peerreview_view_peerreview).
const raw = this.CONTENT_OTHERDATA.data;
this.state = typeof raw === 'string' ? JSON.parse(raw) : raw;
this.review = null;
this.busy = false;

const site = () => this.CoreSitesProvider.getCurrentSite();

// Log the view, like view.php does. A failure here must not stop the page.
site().write('mod_peerreview_view_peerreview', {cmid: this.state.cmid}).catch(() => null);

// Open the simple review form for one card of the list.
this.openReview = async (card) => {
    this.busy = true;
    try {
        const review = await site().read(
            'mod_peerreview_get_review',
            {cmid: this.state.cmid, allocid: card.allocid},
            {getFromCache: false, saveToCache: false}
        );
        review.score = review.score === '' ? null : review.score;
        this.review = review;
    } catch (error) {
        this.CoreDomUtilsProvider.showErrorModal(error);
    } finally {
        this.busy = false;
    }
};

this.closeReview = () => {
    this.review = null;
};

// Submit the review. The server checks everything again (reviewer, open window, score range).
this.submitReview = async () => {
    const review = this.review;
    if (review.score === null || review.score === undefined || review.score === '') {
        this.CoreDomUtilsProvider.showErrorModal(this.state.strings.score);
        return;
    }
    this.busy = true;
    try {
        await site().write('mod_peerreview_save_review', {
            cmid: this.state.cmid,
            allocid: review.allocid,
            score: String(review.score),
            feedback: review.feedback || '',
        });
        this.review = null;
        this.CoreDomUtilsProvider.showToast(this.state.strings.submitted, false, 3000);
        await this.refreshContent(false);
    } catch (error) {
        this.CoreDomUtilsProvider.showErrorModal(error);
    } finally {
        this.busy = false;
    }
};
