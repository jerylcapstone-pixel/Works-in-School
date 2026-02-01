/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Classes/Class.java to edit this template
 */
package TimeTime;

/**
 *
 * @author Jeryl
 */
public class Retriever {
    private static String clogEmployeeName;
    private static String clogEmployeeDep;
    private static String clogEmployeeID;

    public static String getCurrentEmployeeName() {
        return  clogEmployeeName;
    }

    public static void setCurrentEmployeeName(String EmployeeName) {
        clogEmployeeName = EmployeeName;
    }
    public static String getCurrentEmployeeDep() {
        return  clogEmployeeDep;
    }

    public static void setCurrentEmployeeDep(String EmployeeDep) {
        clogEmployeeDep = EmployeeDep;
    }
    public static String getCurrentEmployeeID() {
        return  clogEmployeeID;
    }

    public static void setCurrentEmployeeID(String EmployeeID) {
        clogEmployeeID = EmployeeID;
    }
}

