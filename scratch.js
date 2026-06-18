const matchedProduct = { name: "Wheat Flour 112", price: 120 };
const lowerLine = "1 wheat flour 112 12 120.00 1440.00 18%";
const lineWithoutName = lowerLine.replace(matchedProduct.name.toLowerCase(), '');
const numbers = lineWithoutName.match(/[\d,\.]+/g) || [];
const cleanNumbers = numbers.map(n => n.replace(/,/g, '')).filter(n => !isNaN(parseFloat(n)) && n !== '.');
console.log(cleanNumbers);
