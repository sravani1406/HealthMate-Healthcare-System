import json
import sys
import numpy as np
import pandas as pd
from datetime import datetime, timedelta
import warnings
warnings.filterwarnings('ignore')

class RiskPredictor:
    def __init__(self):
        self.risk_factors = {
            'age': {
                (0, 18): 0.1,
                (19, 30): 0.2,
                (31, 50): 0.4,
                (51, 65): 0.6,
                (66, 120): 0.8
            },
            'gender': {
                'male': 0.6,
                'female': 0.5,
                'other': 0.5
            },
            'medical_conditions': {
                'diabetes': 0.7,
                'hypertension': 0.6,
                'heart disease': 0.8,
                'cancer': 0.9,
                'obesity': 0.5,
                'smoking': 0.7,
                'alcohol abuse': 0.6,
                'depression': 0.4,
                'anxiety': 0.3
            }
        }
        
        self.symptom_frequency_weights = {
            'frequent': 0.8,
            'occasional': 0.4,
            'rare': 0.1
        }

    def calculate_demographic_risk(self, age, gender):
        """Calculate risk based on demographic factors"""
        age_risk = 0.2
        for age_range, risk in self.risk_factors['age'].items():
            if age_range[0] <= age <= age_range[1]:
                age_risk = risk
                break
        
        gender_risk = self.risk_factors['gender'].get(gender.lower(), 0.5)
        
        return (age_risk + gender_risk) / 2

    def calculate_medical_history_risk(self, medical_history):
        """Calculate risk based on medical history"""
        if not medical_history:
            return 0.1
        
        history_lower = medical_history.lower()
        risk_score = 0.0
        condition_count = 0
        
        for condition, risk in self.risk_factors['medical_conditions'].items():
            if condition in history_lower:
                risk_score += risk
                condition_count += 1
        
        if condition_count == 0:
            return 0.1
        
        # Average risk, but cap it
        return min(risk_score / condition_count, 1.0)

    def analyze_symptom_patterns(self, symptom_history):
        """Analyze patterns in symptom history"""
        if not symptom_history:
            return 0.1
        
        pattern_risk = 0.0
        
        # Look for recurring symptoms
        symptom_counts = {}
        for analysis in symptom_history:
            symptoms = json.loads(analysis.get('symptoms', '[]'))
            for symptom in symptoms:
                symptom_name = symptom.get('symptom', '').lower()
                if symptom_name:
                    symptom_counts[symptom_name] = symptom_counts.get(symptom_name, 0) + 1
        
        # Calculate risk based on recurring symptoms
        for symptom, count in symptom_counts.items():
            if count > 2:  # Recurring symptom
                frequency = 'frequent' if count > 5 else 'occasional'
                pattern_risk += self.symptom_frequency_weights[frequency]
        
        return min(pattern_risk, 1.0)

    def predict_future_risks(self, current_risk_level, age, medical_history):
        """Predict potential future health risks"""
        risks = []
        
        # Age-related risks
        if age > 50:
            risks.append({
                'condition': 'Cardiovascular Disease',
                'probability': 0.3 + (age - 50) * 0.01,
                'timeframe': '5-10 years'
            })
        
        if age > 60:
            risks.append({
                'condition': 'Type 2 Diabetes',
                'probability': 0.2 + (age - 60) * 0.015,
                'timeframe': '3-7 years'
            })
        
        # Medical history-based risks
        if medical_history:
            history_lower = medical_history.lower()
            
            if 'diabetes' in history_lower:
                risks.append({
                    'condition': 'Diabetic Complications',
                    'probability': 0.4,
                    'timeframe': '2-5 years'
                })
            
            if 'hypertension' in history_lower:
                risks.append({
                    'condition': 'Stroke Risk',
                    'probability': 0.25,
                    'timeframe': '5-10 years'
                })
            
            if 'smoking' in history_lower:
                risks.append({
                    'condition': 'Lung Disease',
                    'probability': 0.35,
                    'timeframe': '3-8 years'
                })
        
        # Current risk level influence
        if current_risk_level in ['high', 'critical']:
            for risk in risks:
                risk['probability'] *= 1.3  # Increase all risks by 30%
        
        return sorted(risks, key=lambda x: x['probability'], reverse=True)[:5]

    def generate_prevention_recommendations(self, predicted_risks, age, medical_history):
        """Generate prevention recommendations"""
        recommendations = []
        
        # General recommendations
        recommendations.extend([
            "Maintain regular exercise routine (150 minutes moderate activity per week)",
            "Follow a balanced, nutritious diet rich in fruits and vegetables",
            "Get adequate sleep (7-9 hours per night)",
            "Manage stress through relaxation techniques or counseling"
        ])
        
        # Age-specific recommendations
        if age > 40:
            recommendations.append("Schedule annual comprehensive health checkups")
        if age > 50:
            recommendations.append("Consider regular screenings for cancer and heart disease")
        if age > 65:
            recommendations.append("Monitor bone health and consider fall prevention measures")
        
        # Risk-specific recommendations
        high_risk_conditions = [risk['condition'].lower() for risk in predicted_risks if risk['probability'] > 0.3]
        
        if any('cardiovascular' in condition for condition in high_risk_conditions):
            recommendations.extend([
                "Monitor blood pressure regularly",
                "Limit sodium intake and processed foods",
                "Consider cardio exercises like walking or swimming"
            ])
        
        if any('diabetes' in condition for condition in high_risk_conditions):
            recommendations.extend([
                "Monitor blood sugar levels",
                "Maintain healthy weight",
                "Limit refined sugars and carbohydrates"
            ])
        
        if medical_history and 'smoking' in medical_history.lower():
            recommendations.append("Consider smoking cessation programs and support")
        
        return list(set(recommendations))  # Remove duplicates

    def calculate_overall_risk(self, age, gender, medical_history, symptom_history):
        """Calculate overall health risk score"""
        demographic_risk = self.calculate_demographic_risk(age, gender)
        medical_risk = self.calculate_medical_history_risk(medical_history)
        pattern_risk = self.analyze_symptom_patterns(symptom_history)
        
        # Weighted average
        overall_risk = (
            demographic_risk * 0.3 +
            medical_risk * 0.5 +
            pattern_risk * 0.2
        )
        
        return min(overall_risk, 1.0)

    def predict_health_risks(self, patient_data):
        """Main prediction function"""
        try:
            age = patient_data.get('age', 30)
            gender = patient_data.get('gender', 'unknown')
            medical_history = patient_data.get('medical_history', '')
            symptom_history = patient_data.get('symptom_history', [])
            current_risk = patient_data.get('current_risk_level', 'low')
            
            # Calculate overall risk
            overall_risk = self.calculate_overall_risk(age, gender, medical_history, symptom_history)
            
            # Determine risk category
            if overall_risk >= 0.7:
                risk_category = 'high'
            elif overall_risk >= 0.4:
                risk_category = 'moderate'
            else:
                risk_category = 'low'
            
            # Predict future risks
            future_risks = self.predict_future_risks(current_risk, age, medical_history)
            
            # Generate recommendations
            recommendations = self.generate_prevention_recommendations(future_risks, age, medical_history)
            
            return {
                'overall_risk_score': round(overall_risk, 2),
                'risk_category': risk_category,
                'predicted_risks': future_risks,
                'prevention_recommendations': recommendations,
                'analysis_date': datetime.now().isoformat(),
                'confidence': 0.75  # Model confidence score
            }
        
        except Exception as e:
            return {
                'error': f'Risk prediction failed: {str(e)}',
                'overall_risk_score': 0.0,
                'risk_category': 'unknown',
                'predicted_risks': [],
                'prevention_recommendations': ['Consult with a healthcare provider for proper risk assessment.']
            }

def main():
    """Main function to handle command line input"""
    try:
        if len(sys.argv) < 2:
            print(json.dumps({'error': 'No input data provided'}))
            sys.exit(1)
        
        # Parse input JSON
        input_data = json.loads(sys.argv[1])
        
        # Initialize predictor
        predictor = RiskPredictor()
        
        # Predict risks
        result = predictor.predict_health_risks(input_data)
        
        # Output result as JSON
        print(json.dumps(result))
        
    except Exception as e:
        error_result = {
            'error': f'Prediction failed: {str(e)}',
            'overall_risk_score': 0.0,
            'risk_category': 'unknown',
            'predicted_risks': [],
            'prevention_recommendations': ['Please consult a healthcare provider for proper risk assessment.']
        }
        print(json.dumps(error_result))

if __name__ == '__main__':
    main()
