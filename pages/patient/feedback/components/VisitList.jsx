/**
 * Visit List Component
 * Displays completed visits with feedback status
 */

const VisitList = ({ visits, onVisitSelect, feedback }) => {
    const formatDate = (dateStr) => {
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } catch {
            return dateStr;
        }
    };

    const formatTime = (timeStr) => {
        try {
            if (!timeStr) return '';
            const [hours, minutes] = timeStr.split(':');
            const date = new Date();
            date.setHours(parseInt(hours), parseInt(minutes));
            return date.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        } catch {
            return timeStr;
        }
    };

    const hasFeedback = (visitId) => {
        return feedback.some(f => f.visit_id === visitId);
    };

    const getFeedbackStatus = (visit) => {
        const hasSubmitted = visit.has_feedback || hasFeedback(visit.visit_id);
        return {
            status: hasSubmitted ? 'completed' : 'pending',
            text: hasSubmitted ? 'Feedback Submitted' : 'Feedback Pending',
            icon: hasSubmitted ? 'fas fa-check-circle' : 'fas fa-clock'
        };
    };

    if (!visits || visits.length === 0) {
        return (
            <div className="empty-state">
                <i className="fas fa-calendar-times"></i>
                <h3>No Completed Visits</h3>
                <p>You don't have any completed visits yet. Complete a visit to provide feedback about your experience.</p>
            </div>
        );
    }

    return (
        <div>
            <div className="form-header">
                <h2>Your Completed Visits</h2>
                <p>Select a visit to provide feedback about your healthcare experience</p>
            </div>

            <div className="visit-list">
                {visits.map((visit, index) => {
                    const feedbackStatus = getFeedbackStatus(visit);
                    
                    return (
                        <div key={visit.visit_id} className="visit-card">
                            <div className="visit-header">
                                <div>
                                    <div className="visit-date">
                                        {formatDate(visit.visit_date)}
                                    </div>
                                    <div style={{ fontSize: '14px', color: '#6c757d', marginTop: '5px' }}>
                                        {formatTime(visit.visit_time)} • {visit.facility_name}
                                    </div>
                                </div>
                                <div className={`feedback-status ${feedbackStatus.status}`}>
                                    <i className={feedbackStatus.icon}></i>
                                    {feedbackStatus.text}
                                </div>
                            </div>

                            <div className="visit-details">
                                {visit.diagnosis && (
                                    <div style={{ marginBottom: '8px' }}>
                                        <strong>Diagnosis:</strong> {visit.diagnosis}
                                    </div>
                                )}
                                {visit.treatment_notes && (
                                    <div>
                                        <strong>Treatment:</strong> {visit.treatment_notes}
                                    </div>
                                )}
                                {!visit.diagnosis && !visit.treatment_notes && (
                                    <div style={{ fontStyle: 'italic' }}>
                                        General consultation and health check
                                    </div>
                                )}
                            </div>

                            <div className="visit-actions">
                                {feedbackStatus.status === 'pending' ? (
                                    <button 
                                        className="btn btn-primary"
                                        onClick={() => onVisitSelect(visit)}
                                    >
                                        <i className="fas fa-edit"></i>
                                        Provide Feedback
                                    </button>
                                ) : (
                                    <>
                                        <button 
                                            className="btn btn-outline"
                                            onClick={() => onVisitSelect(visit)}
                                        >
                                            <i className="fas fa-eye"></i>
                                            View Feedback
                                        </button>
                                        <button 
                                            className="btn btn-success"
                                            onClick={() => onVisitSelect(visit)}
                                        >
                                            <i className="fas fa-edit"></i>
                                            Edit Feedback
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Statistics Summary */}
            <div style={{ 
                marginTop: '30px', 
                padding: '25px', 
                background: 'linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%)', 
                borderRadius: '12px',
                textAlign: 'center'
            }}>
                <div style={{ display: 'flex', justifyContent: 'space-around', flexWrap: 'wrap', gap: '20px' }}>
                    <div>
                        <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#667eea' }}>
                            {visits.length}
                        </div>
                        <div style={{ fontSize: '14px', color: '#6c757d' }}>
                            Total Visits
                        </div>
                    </div>
                    <div>
                        <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#28a745' }}>
                            {visits.filter(v => v.has_feedback || hasFeedback(v.visit_id)).length}
                        </div>
                        <div style={{ fontSize: '14px', color: '#6c757d' }}>
                            Feedback Submitted
                        </div>
                    </div>
                    <div>
                        <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#ffc107' }}>
                            {visits.filter(v => !v.has_feedback && !hasFeedback(v.visit_id)).length}
                        </div>
                        <div style={{ fontSize: '14px', color: '#6c757d' }}>
                            Pending Feedback
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};