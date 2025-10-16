/**
 * Main Feedback App Component
 * Manages navigation between visits, forms, and history
 */

const { useState, useEffect } = React;

const FeedbackApp = ({ patientData }) => {
    const [activeTab, setActiveTab] = useState('visits');
    const [selectedVisit, setSelectedVisit] = useState(null);
    const [feedback, setFeedback] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    // Load feedback history on mount
    useEffect(() => {
        loadFeedbackHistory();
    }, []);

    const loadFeedbackHistory = async () => {
        try {
            setLoading(true);
            const response = await axios.get(
                `${patientData.api_base_url}summary.php?patient_id=${patientData.patient_id}`
            );
            
            if (response.data.success) {
                setFeedback(response.data.data.feedback || []);
            }
        } catch (err) {
            console.error('Error loading feedback history:', err);
        } finally {
            setLoading(false);
        }
    };

    const handleTabChange = (tab) => {
        setActiveTab(tab);
        setSelectedVisit(null);
        setError(null);
    };

    const handleVisitSelect = (visit) => {
        setSelectedVisit(visit);
        setActiveTab('form');
    };

    const handleFeedbackSubmit = async (feedbackData) => {
        try {
            setLoading(true);
            setError(null);

            const response = await axios.post(
                `${patientData.api_base_url}submit.php`,
                {
                    visit_id: selectedVisit.visit_id,
                    patient_id: patientData.patient_id,
                    facility_id: selectedVisit.facility_id,
                    submitted_by: 'patient',
                    answers: feedbackData
                }
            );

            if (response.data.success) {
                // Refresh data
                await loadFeedbackHistory();
                
                // Show success message
                setError(null);
                setActiveTab('history');
                
                // Update visit status in local state
                const updatedVisits = patientData.completed_visits.map(visit => 
                    visit.visit_id === selectedVisit.visit_id 
                        ? { ...visit, has_feedback: 1 }
                        : visit
                );
                patientData.completed_visits = updatedVisits;
            } else {
                setError(response.data.error?.message || 'Failed to submit feedback');
            }
        } catch (err) {
            console.error('Error submitting feedback:', err);
            setError('Network error. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const handleBackToVisits = () => {
        setSelectedVisit(null);
        setActiveTab('visits');
        setError(null);
    };

    return (
        <div className="feedback-container">
            {/* Navigation Tabs */}
            <nav className="feedback-nav">
                <button 
                    className={`nav-tab ${activeTab === 'visits' ? 'active' : ''}`}
                    onClick={() => handleTabChange('visits')}
                    disabled={loading}
                >
                    <i className="fas fa-calendar-check"></i>
                    My Visits
                </button>
                <button 
                    className={`nav-tab ${activeTab === 'form' ? 'active' : ''}`}
                    onClick={() => handleTabChange('form')}
                    disabled={!selectedVisit || loading}
                >
                    <i className="fas fa-edit"></i>
                    Feedback Form
                </button>
                <button 
                    className={`nav-tab ${activeTab === 'history' ? 'active' : ''}`}
                    onClick={() => handleTabChange('history')}
                    disabled={loading}
                >
                    <i className="fas fa-history"></i>
                    My Feedback
                </button>
            </nav>

            {/* Error Display */}
            {error && (
                <div className="error-message">
                    <i className="fas fa-exclamation-triangle"></i>
                    {error}
                </div>
            )}

            {/* Tab Content */}
            <div className="tab-content">
                {loading && (
                    <div className="loading">
                        <div>
                            <i className="fas fa-spinner"></i>
                            <p>Loading...</p>
                        </div>
                    </div>
                )}

                {!loading && activeTab === 'visits' && (
                    <VisitList 
                        visits={patientData.completed_visits}
                        onVisitSelect={handleVisitSelect}
                        feedback={feedback}
                    />
                )}

                {!loading && activeTab === 'form' && selectedVisit && (
                    <FeedbackForm 
                        visit={selectedVisit}
                        onSubmit={handleFeedbackSubmit}
                        onCancel={handleBackToVisits}
                        loading={loading}
                    />
                )}

                {!loading && activeTab === 'history' && (
                    <FeedbackHistory 
                        feedback={feedback}
                        visits={patientData.completed_visits}
                        onEditFeedback={(visit) => handleVisitSelect(visit)}
                    />
                )}

                {!loading && activeTab === 'form' && !selectedVisit && (
                    <div className="empty-state">
                        <i className="fas fa-clipboard-list"></i>
                        <h3>No Visit Selected</h3>
                        <p>Please select a visit from the "My Visits" tab to provide feedback.</p>
                        <button 
                            className="btn btn-primary"
                            onClick={() => handleTabChange('visits')}
                        >
                            View My Visits
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
};