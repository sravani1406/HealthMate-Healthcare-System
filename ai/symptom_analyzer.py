import json
import sys
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.preprocessing import LabelEncoder
import warnings
warnings.filterwarnings('ignore')

class SymptomAnalyzer:
    def __init__(self):
        self.symptom_weights = {
            # Critical symptoms (immediate medical attention)
            'chest pain': 0.9,
            'difficulty breathing': 0.9,
            'shortness of breath': 0.9,
            'severe headache': 0.8,
            'loss of consciousness': 1.0,
            'severe abdominal pain': 0.8,
            'blood in vomit': 0.9,
            'blood in stool': 0.8,
            'severe allergic reaction': 0.9,
            
            # High priority symptoms
            'fever': 0.6,
            'persistent cough': 0.5,
            'severe fatigue': 0.4,
            'unexplained weight loss': 0.7,
            'persistent headache': 0.6,
            'nausea': 0.3,
            'vomiting': 0.4,
            'diarrhea': 0.3,
            
            # Medium priority symptoms
            'muscle aches': 0.2,
            'joint pain': 0.3,
            'back pain': 0.2,
            'neck pain': 0.3,
            'sore throat': 0.2,
            'runny nose': 0.1,
            'sneezing': 0.1,
            
            # Low priority symptoms
            'mild headache': 0.1,
            'minor fatigue': 0.1,
            'slight dizziness': 0.2,
        }
        
        self.severity_multipliers = {
            'mild': 1.0,
            'moderate': 1.5,
            'severe': 2.0
        }
        
        self.duration_multipliers = {
            'recent': 0.8,
            'few_hours': 1.0,
            'today': 1.1,
            'few_days': 1.3,
            'week': 1.5,
            'weeks': 1.8,
            'months': 2.0
        }
        
        # Age risk factors
        self.age_risk_factors = {
            (0, 5): 1.2,      # Infants and toddlers
            (6, 17): 0.8,     # Children and teens
            (18, 64): 1.0,    # Adults
            (65, 120): 1.4    # Elderly
        }
        
        # Gender-specific risk factors for certain conditions
        self.gender_risk_factors = {
            'male': {
                'chest pain': 1.2,
                'heart palpitations': 1.1
            },
            'female': {
                'abdominal pain': 1.1,
                'headache': 1.1,
                'fatigue': 1.1
            }
        }

    def calculate_base_risk(self, symptoms_data, age, gender):
        """Calculate base risk score from symptoms"""
        total_risk = 0.0
        symptom_count = len(symptoms_data)
        
        for symptom_info in symptoms_data:
            symptom = symptom_info['symptom'].lower()
            severity = symptom_info.get('severity', 'mild')
            duration = symptom_info.get('duration', 'recent')
            
            # Get base weight for symptom
            base_weight = self.symptom_weights.get(symptom, 0.1)
            
            # Apply severity multiplier
            severity_mult = self.severity_multipliers.get(severity, 1.0)
            
            # Apply duration multiplier
            duration_mult = self.duration_multipliers.get(duration, 1.0)
            
            # Apply gender-specific risk factors
            gender_mult = 1.0
            if gender in self.gender_risk_factors:
                gender_mult = self.gender_risk_factors[gender].get(symptom, 1.0)
            
            symptom_risk = base_weight * severity_mult * duration_mult * gender_mult
            total_risk += symptom_risk
        
        # Apply age risk factor
        age_mult = 1.0
        for age_range, multiplier in self.age_risk_factors.items():
            if age_range[0] <= age <= age_range[1]:
                age_mult = multiplier
                break
        
        total_risk *= age_mult
        
        # Normalize based on number of symptoms
        if symptom_count > 1:
            total_risk *= (1 + (symptom_count - 1) * 0.2)  # Multiple symptoms increase risk
        
        return min(total_risk, 1.0)  # Cap at 1.0

    def get_possible_conditions(self, symptoms_data):
        """Suggest possible conditions based on symptoms"""
        symptoms = [s['symptom'].lower() for s in symptoms_data]
        conditions = []
        
        # Common condition patterns
        condition_patterns = {
            'common_cold': ['runny nose', 'sneezing', 'sore throat', 'mild headache'],
            'flu': ['fever', 'muscle aches', 'headache', 'fatigue', 'cough'],
            'gastroenteritis': ['nausea', 'vomiting', 'diarrhea', 'abdominal pain'],
            'migraine': ['severe headache', 'nausea', 'sensitivity to light'],
            'food_poisoning': ['nausea', 'vomiting', 'diarrhea', 'abdominal pain', 'fever'],
            'anxiety': ['chest pain', 'shortness of breath', 'dizziness', 'rapid heartbeat'],
            'dehydration': ['dizziness', 'fatigue', 'headache', 'nausea'],
            'muscle_strain': ['muscle aches', 'back pain', 'neck pain', 'joint pain']
        }
        
        for condition, pattern_symptoms in condition_patterns.items():
            matches = sum(1 for s in symptoms if any(ps in s for ps in pattern_symptoms))
            if matches >= 2:  # At least 2 symptom matches
                confidence = matches / len(pattern_symptoms)
                conditions.append({
                    'condition': condition.replace('_', ' ').title(),
                    'confidence': confidence
                })
        
        # Sort by confidence
        conditions.sort(key=lambda x: x['confidence'], reverse=True)
        return conditions[:3]  # Return top 3 matches

    def get_recommendations(self, risk_level, symptoms_data):
        """Generate recommendations based on risk level and symptoms"""
        recommendations = []
        symptoms = [s['symptom'].lower() for s in symptoms_data]
        
        if risk_level == 'critical':
            recommendations = [
                "Seek immediate emergency medical attention",
                "Call emergency services (911) if symptoms are severe",
                "Do not delay treatment",
                "Have someone accompany you to the hospital"
            ]
        elif risk_level == 'high':
            recommendations = [
                "Consult with a healthcare provider today",
                "Monitor symptoms closely",
                "Avoid strenuous activities",
                "Stay hydrated and rest"
            ]
        elif risk_level == 'medium':
            recommendations = [
                "Schedule an appointment with your doctor within 2-3 days",
                "Monitor symptoms and note any changes",
                "Get adequate rest and hydration",
                "Take over-the-counter medications as appropriate"
            ]
        else:  # low risk
            recommendations = [
                "Monitor symptoms for 24-48 hours",
                "Rest and stay hydrated",
                "Consider over-the-counter remedies",
                "Contact your doctor if symptoms worsen or persist"
            ]
        
        # Add specific recommendations based on symptoms
        if 'fever' in symptoms:
            recommendations.append("Take temperature regularly and manage fever with appropriate medications")
        
        if 'dehydration' in [s for s in symptoms if 'dehydration' in s]:
            recommendations.append("Increase fluid intake, consider oral rehydration solutions")
        
        if any('pain' in s for s in symptoms):
            recommendations.append("Apply appropriate pain management techniques (heat/cold therapy)")
        
        return recommendations

    def determine_risk_level(self, risk_score):
        """Convert risk score to categorical risk level"""
        if risk_score >= 0.8:
            return 'critical'
        elif risk_score >= 0.6:
            return 'high'
        elif risk_score >= 0.3:
            return 'medium'
        else:
            return 'low'

    def analyze_symptoms(self, symptoms_data, age, gender, medical_history=None):
        """Main analysis function"""
        try:
            # Calculate base risk
            risk_score = self.calculate_base_risk(symptoms_data, age, gender)
            
            # Adjust for medical history
            if medical_history:
                high_risk_conditions = ['diabetes', 'hypertension', 'heart disease', 'cancer', 'immunocompromised']
                for condition in high_risk_conditions:
                    if any(condition.lower() in medical_history.lower() for condition in high_risk_conditions):
                        risk_score *= 1.2
                        break
            
            # Determine risk level
            risk_level = self.determine_risk_level(risk_score)
            
            # Get possible conditions
            possible_conditions = self.get_possible_conditions(symptoms_data)
            
            # Get recommendations
            recommendations = self.get_recommendations(risk_level, symptoms_data)
            
            # Determine urgency
            urgency = 'immediate' if risk_level in ['critical'] else \
                     'today' if risk_level == 'high' else \
                     'few_days' if risk_level == 'medium' else 'monitor'
            
            return {
                'risk_level': risk_level,
                'risk_score': round(risk_score, 2),
                'possible_conditions': [c['condition'] for c in possible_conditions],
                'condition_confidences': possible_conditions,
                'recommendations': recommendations,
                'urgency': urgency,
                'analysis_timestamp': pd.Timestamp.now().isoformat()
            }
        
        except Exception as e:
            return {
                'risk_level': 'unknown',
                'risk_score': 0.0,
                'possible_conditions': [],
                'recommendations': ['Unable to analyze symptoms. Please consult a healthcare provider.'],
                'urgency': 'consult_doctor',
                'error': str(e)
            }

def main():
    """Main function to handle command line input"""
    try:
        if len(sys.argv) < 2:
            print(json.dumps({'error': 'No input data provided'}))
            sys.exit(1)
        
        # Parse input JSON
        input_data = json.loads(sys.argv[1])
        
        # Extract data
        symptoms_data = input_data.get('symptoms', [])
        age = input_data.get('age', 30)
        gender = input_data.get('gender', 'unknown')
        medical_history = input_data.get('medical_history', '')
        
        # Initialize analyzer
        analyzer = SymptomAnalyzer()
        
        # Analyze symptoms
        result = analyzer.analyze_symptoms(symptoms_data, age, gender, medical_history)
        
        # Output result as JSON
        print(json.dumps(result))
        
    except Exception as e:
        error_result = {
            'error': f'Analysis failed: {str(e)}',
            'risk_level': 'unknown',
            'recommendations': ['Please consult a healthcare provider for proper evaluation.']
        }
        print(json.dumps(error_result))

if __name__ == '__main__':
    main()
