const lines = [
    "wheat flour 112 12 120.00 1440.00",
    "wheat flour 112 120.00 1440.00",
    "wheat flour 112 12.00 120.00 1440.00",
    "1 wheat flour 112 12 120.00 1440.00",
    "wheat flour  112 12 120 1440",
    "power bank 5 11 110.00 1210.00",
    "power bank  5 11 110 1210",
    "kurta 385 10 100.00 1000.00"
];

const products = [
    { name: "Wheat Flour 112", id: 1 },
    { name: "Wheat Flour", id: 2 },
    { name: "Power Bank 5", id: 3 },
    { name: "Kurta 385", id: 4 }
];

for (const line of lines) {
    const lowerLine = line.toLowerCase();
    const matchedProduct = products.find(p => p.name && lowerLine.includes(p.name.toLowerCase()));
    
    if (matchedProduct) {
        const lineWithoutName = lowerLine.replace(matchedProduct.name.toLowerCase(), '');
        const numbers = lineWithoutName.match(/[\d,\.]+/g) || [];
        const cleanNumbers = numbers.map(n => n.replace(/,/g, '')).filter(n => !isNaN(parseFloat(n)) && n !== '.');

        let qty = 1;
        let price = 0;

        if (cleanNumbers.length >= 2) {
            let num1 = parseFloat(cleanNumbers[0]);
            let num2 = parseFloat(cleanNumbers[1]);
            
            if (Number.isInteger(num1) && num1 < num2) {
                qty = num1;
                price = num2;
            } else if (Number.isInteger(num2) && num2 < num1) {
                qty = num2;
                price = num1;
            } else {
                qty = num1;
                price = num2;
            }
        }
        console.log(`Line: ${line}`);
        console.log(`Matched: ${matchedProduct.name}`);
        console.log(`cleanNumbers:`, cleanNumbers);
        console.log(`=> Qty: ${qty}, Price: ${price}`);
        console.log('---');
    }
}
