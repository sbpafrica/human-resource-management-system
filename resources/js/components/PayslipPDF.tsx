import { Document, Page, Text, View, StyleSheet, Image } from "@react-pdf/renderer";
import { Payslip } from "@/types/Payslip";

// Define styles to match pdf.blade.php
const styles = StyleSheet.create({
    page: {
        padding: 20, // Minimal page margin
        fontFamily: "Helvetica", // Closest to Arial
        fontSize: 12, // Match HTML body font-size: 12px
    },
    table: {
        width: "100%",
        borderStyle: "solid",
        borderWidth: 1,
        borderColor: "#ccc", // Match HTML border: 1px solid #ccc
        borderCollapse: "collapse",
    },
    tableRow: {
        flexDirection: "row",
    },
    tableCell: {
        borderStyle: "solid",
        borderWidth: 1,
        borderColor: "#ccc",
        padding: 8, // Match HTML padding: 8px
        fontSize: 12,
        textAlign: "left", // Default text alignment
        flexGrow: 1,
        flexShrink: 1,
    },
    headerCell: {
        fontWeight: "bold",
        textAlign: "center", // Match HTML th.header text-align: center
        backgroundColor: "#4b5563", // Match modal's th background
        color: "#ffffff",
    },
    logo: {
        width: 150, // Match HTML .logo width: 150px
        height: "auto",
    },
    salarySlip: {
        fontSize: 18, // Approximate HTML <h2> size
        fontWeight: "bold",
        textAlign: "center",
        color: "#f97316", // Orange to match modal
    },
    confidential: {
        fontSize: 18,
        fontWeight: "bold",
        textAlign: "center",
        color: "#000000", // Black to match HTML
    },
    month: {
        fontSize: 12,
        textAlign: "center",
    },
    boldText: {
        fontWeight: "bold",
    },
    totalText: {
        fontWeight: "bold",
        color: "#000000", // Black to match HTML .total
    },
    netPayText: {
        fontWeight: "bold",
        color: "#000000", // Black to match HTML .net-pay
    },
    rightText: {
        textAlign: "right", // Match HTML text-align: right for Description/Days/Hours
    },
    centerText: {
        textAlign: "center", // Match HTML text-align: center for Earnings/Deductions
    },
});

interface Props {
    payslip: Payslip | null;
    formattedMonth: string;
}

const PayslipPDF = ({ payslip, formattedMonth }: Props) => {
    // Fallback for null payslip
    if (!payslip) {
        return (
            <Document>
                <Page size="A4" style={styles.page}>
                    <View style={{ margin: 10 }}>
                        <Text style={{ fontSize: 14, fontWeight: "bold", textAlign: "center" }}>
                            No Payslip Data Available
                        </Text>
                        <Text style={{ fontSize: 12, textAlign: "center" }}>
                            Please select a valid Staff ID and month to generate the payslip.
                        </Text>
                    </View>
                </Page>
            </Document>
        );
    }

    return (
        <Document title={`Payslip - ${payslip.name} - ${formattedMonth}`}>
            <Page size="A4" style={styles.page}>
                <View style={styles.table}>
                    {/* Header Row: Logo, SALARY SLIP, CONFIDENTIAL */}
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}>
                            <Image style={styles.logo} src="/images/sbpafricalogo.png" />
                        </View>
                        <View style={[styles.tableCell, { flex: 1 }, styles.centerText]}>
                            <Text style={styles.salarySlip}>SALARY SLIP</Text>
                        </View>
                        <View style={[styles.tableCell, { flex: 2 }, styles.centerText]}>
                            <Text style={styles.confidential}>CONFIDENTIAL</Text>
                        </View>
                    </View>
                    {/* Month Row */}
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}></View>
                        <View style={[styles.tableCell, { flex: 1 }, styles.centerText]}>
                            <Text style={styles.month}>{formattedMonth}</Text>
                        </View>
                        <View style={[styles.tableCell, { flex: 2 }]}></View>
                    </View>
                    {/* Employee Info */}
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>Name</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>{payslip.name || "N/A"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>Title</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>{payslip.position || "N/A"}</Text></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>Employee ID</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>{payslip.staffida || "N/A"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>Client Company</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>{payslip.brandname || "N/A"}</Text></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>SSNIT #</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>{payslip.ssnit || "N/A"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>Department</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>{payslip.department || "N/A"}</Text></View>
                    </View>
                    {/* Table Headers */}
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, styles.headerCell, { flex: 2 }]}><Text>Item</Text></View>
                        <View style={[styles.tableCell, styles.headerCell, { flex: 1 }]}><Text>Description/Days/Hours</Text></View>
                        <View style={[styles.tableCell, styles.headerCell, { flex: 1 }]}><Text>Earnings</Text></View>
                        <View style={[styles.tableCell, styles.headerCell, { flex: 1 }]}><Text>Deductions</Text></View>
                    </View>
                    {/* Earnings and Deductions */}
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>Basic Salary</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }, styles.rightText]}><Text>{payslip.workdays ?? 0}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }, styles.centerText]}><Text>{payslip.basic?.toFixed(2) ?? "0.00"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>Performance Bonus</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }, styles.rightText]}><Text>{payslip.coefficient ?? 0}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }, styles.centerText]}><Text>{payslip.performstand?.toFixed(2) ?? "0.00"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>Allowance</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, { flex: 1 }, styles.centerText]}><Text>{payslip.allowance?.toFixed(2) ?? "0.00"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>Overtime Weekday</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }, styles.rightText]}><Text>{payslip.otworkdays ?? payslip.normalOThrs ?? payslip.weekdaysOT ?? 0}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }, styles.centerText]}><Text>{(payslip.otworkingday ?? payslip.normalovertime)?.toFixed(2) ?? "0.00"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>Overtime Weekend/Holiday</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }, styles.rightText]}><Text>{payslip.otweekend ?? payslip.weekendOThrs ?? payslip.weekendOT ?? 0}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }, styles.centerText]}><Text>{(payslip.otholiday ?? payslip.saturdayovertime)?.toFixed(2) ?? "0.00"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>Other Income</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, { flex: 1 }, styles.centerText]}><Text>{payslip.other?.toFixed(2) ?? "0.00"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>Commission</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, { flex: 1 }, styles.centerText]}><Text>{payslip.commission?.toFixed(2) ?? "0.00"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, styles.boldText, { flex: 2 }]}><Text>Gross Salary</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, styles.boldText, styles.centerText, { flex: 1 }]}><Text>{payslip.grosspay?.toFixed(2) ?? "0.00"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>Annual Bonus</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, styles.boldText, styles.centerText, { flex: 1 }]}><Text>{payslip.annaulbonus?.toFixed(2) ?? "0.00"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>SSNIT</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, styles.centerText, { flex: 1 }]}><Text>{payslip.ssnit_contribution?.toFixed(2) ?? "0.00"}</Text></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>Normal Tax-Regular</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, styles.centerText, { flex: 1 }]}><Text>{payslip.paye?.toFixed(2) ?? "0.00"}</Text></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>Leave</Text></View>
                        <View style={[styles.tableCell, styles.centerText, { flex: 1 }]}><Text>{payslip.leavedays ?? 0}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, styles.centerText, { flex: 1 }]}><Text>{payslip.leavededuction?.toFixed(2) ?? "0.00"}</Text></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>Other Deduction</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, styles.centerText, { flex: 1 }]}><Text>{payslip.otherdeduction?.toFixed(2) ?? "0.00"}</Text></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>Loan</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, styles.centerText, { flex: 1 }]}><Text>{payslip.loan?.toFixed(2) ?? "0.00"}</Text></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 2 }]}><Text>Annual Tax</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, styles.centerText, { flex: 1 }]}><Text>{payslip.annaultax?.toFixed(2) ?? "0.00"}</Text></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, styles.totalText, { flex: 3 }]}><Text>Total</Text></View>
                        <View style={[styles.tableCell, styles.totalText, styles.centerText, { flex: 1 }]}><Text>{payslip.total_earnings?.toFixed(2) ?? "0.00"}</Text></View>
                        <View style={[styles.tableCell, styles.totalText, styles.centerText, { flex: 1 }]}><Text>{payslip.total_deductions?.toFixed(2) ?? "0.00"}</Text></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>Bank Name</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>{payslip.bankname || "N/A"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, styles.netPayText, { flex: 2 }]}><Text>NET PAY</Text></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>Bank Account Name</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>{payslip.name || "N/A"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, styles.netPayText, styles.centerText, { flex: 2 }]}><Text>{payslip.netpay?.toFixed(2) ?? "0.00"}</Text></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>Bank Account #</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>{payslip.alcnum || "N/A"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, { flex: 2 }]}></View>
                    </View>
                    <View style={styles.tableRow}>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>Bank Branch</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}><Text>{payslip.branch || "N/A"}</Text></View>
                        <View style={[styles.tableCell, { flex: 1 }]}></View>
                        <View style={[styles.tableCell, { flex: 2 }]}></View>
                    </View>
                </View>
            </Page>
        </Document>
    );
};

export default PayslipPDF;
