# Fructose Filter Clarification

## 📊 **Fructose Value Reference**

### **API Data Structure**
The CookButler API provides fructose values in **two different contexts**:

```json
"Fructose (Fruchtzucker)": {
    "substance": "Fructose (Fruchtzucker)",
    "unit_short": "g",
    "recipe": {"amount": 13.24, "amount_max": 13.24},    // per 100g of ingredients
    "portion": {"amount": 3.31, "amount_max": 3.31}      // per serving/portion
}
```

### **Filter Values Reference**
**The fructose filter values refer to `recipe.amount` (per 100g of ingredients), NOT per portion.**

- **Filter value**: 50 mg/100g
- **Means**: Less than or equal to 50mg fructose per 100g of ingredients
- **NOT**: Per serving/portion

### **Why This Matters**
- **Per 100g**: Standardized comparison across different recipe sizes
- **Per portion**: Varies based on serving size, not comparable
- **Bundeslebensmittelschlüssel**: Uses per 100g values for consistency

### **Display Logic**
- **Recipe view**: Shows `portion.amount` (per serving) for user convenience
- **Filter interface**: Uses `recipe.amount` (per 100g) for consistent filtering
- **Units now displayed**: `mg/100g` to clarify the reference

### **Example**
- **Recipe**: Apple pie with 200g apples per 100g total ingredients
- **Apples contain**: ~6g fructose per 100g
- **Recipe fructose**: ~12g per 100g total ingredients
- **Filter < 50mg/100g**: This recipe would be **excluded** (12g = 12,000mg > 50mg)
- **Per portion**: Recipe might show 2.4g fructose per serving, but filter still uses per 100g value

## 🎯 **Customer Clarification**
The customer's confusion was justified - the interface didn't clearly show that filter values refer to **per 100g of ingredients**, not per portion. This has now been clarified with unit labels.
