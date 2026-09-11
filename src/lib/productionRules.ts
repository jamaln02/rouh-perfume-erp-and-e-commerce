/**
 * ROUH production rule: alcohol fills the remaining bottle volume after the
 * entered oil quantity. The project intentionally uses grams as the numeric
 * quantity subtracted from millilitres; no density conversion is applied.
 */
export const calculateResidualAlcoholMl = (bottleSizeMl: number, oilGrams: number): number =>
  Math.max(0, Number(bottleSizeMl || 0) - Number(oilGrams || 0));
