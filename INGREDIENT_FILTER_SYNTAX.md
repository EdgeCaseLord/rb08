# Ingredient Filter Syntax Guide

## 🔍 **Supported Logic Operations**

### **AND Logic (Default)**
- **Syntax**: Space-separated terms
- **Example**: `paprika nudeln`
- **Result**: Recipes containing BOTH paprika AND noodles
- **API Query**: `paprika && nudeln`

### **OR Logic**
- **Syntax**: Forward slash `/`
- **Example**: `paprika / nudeln`
- **Result**: Recipes containing EITHER paprika OR noodles
- **API Query**: `paprika || nudeln`

### **NOT Logic**
- **Syntax**: Minus sign `-` before ingredient
- **Example**: `paprika -aprikosen`
- **Result**: Recipes containing paprika but NOT apricots
- **API Query**: `paprika -- aprikosen`

## 🎯 **Complex Examples**

### **Combined Logic**
- **Input**: `paprika nudeln / tomate -aprikosen`
- **Result**: Recipes with (paprika AND noodles) OR (tomatoes) but NOT apricots
- **API Query**: `paprika && nudeln || tomate -- aprikosen`

### **Multiple OR Groups**
- **Input**: `paprika / nudeln tomate / käse`
- **Result**: Recipes with paprika OR (noodles AND tomatoes) OR cheese
- **API Query**: `paprika || nudeln && tomate || käse`

### **Complex NOT Operations**
- **Input**: `paprika -aprikosen -tomaten nudeln`
- **Result**: Recipes with paprika (but not apricots or tomatoes) AND noodles
- **API Query**: `paprika -- aprikosen -- tomaten && nudeln`

## 🛠 **Technical Implementation**

### **Processing Order**
1. **Normalize spaces**: `[\s,]+` → single space
2. **Convert OR**: `/\s*\/\s*/` → `||`
3. **Convert NOT**: `/\s*-([\wäöüÄÖÜß]+)/u` → `-- $1`
4. **Convert AND**: `/\s+/` → `&&`

### **API Query Format**
- **AND**: `&&` (space-separated terms)
- **OR**: `||` (forward slash)
- **NOT**: `--` (minus sign)

## 📝 **User Interface**

### **Help Text**
- **Tooltip**: Shows syntax examples and tips
- **Placeholder**: `Zutaten (Bsp.: paprika nudeln -aprikosen)`
- **Examples**: Clear AND/OR/NOT examples

### **Validation**
- **German characters**: Supports `äöüÄÖÜß`
- **Case insensitive**: API handles case conversion
- **Whitespace**: Normalized to single spaces

## 🎯 **Customer Use Cases**

### **Fructose Intolerance**
- **Query**: `-apfel -birne -honig`
- **Result**: Recipes without apples, pears, or honey

### **Vegetarian + Specific Ingredients**
- **Query**: `käse nudeln / reis tomaten`
- **Result**: Cheese noodles OR rice with tomatoes

### **Allergy Avoidance**
- **Query**: `nudeln -gluten -milch`
- **Result**: Noodles without gluten or milk

## ✅ **Testing Examples**

| Input | Expected Result | API Query |
|-------|----------------|-----------|
| `paprika nudeln` | Paprika AND noodles | `paprika && nudeln` |
| `paprika / nudeln` | Paprika OR noodles | `paprika || nudeln` |
| `paprika -aprikosen` | Paprika but NOT apricots | `paprika -- aprikosen` |
| `paprika nudeln / tomate` | (Paprika AND noodles) OR tomatoes | `paprika && nudeln || tomate` |
